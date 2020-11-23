<?php
declare(strict_types=1);

namespace Keenwork;

use Keenwork\Request;
use Keenwork\Response;
use Keenwork\Factory\CometPsr17Factory;
use Keenwork\Middleware\JsonBodyParserMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use Slim\Factory\AppFactory;
use Slim\Factory\Psr17\Psr17FactoryProvider;
use Slim\Exception\HttpNotFoundException;
use Workerman\Worker;
use Workerman\Protocols\Http\Request as WorkermanRequest;
use Workerman\Protocols\Http\Response as WorkermanResponse;
use Slim\App;

class Keenwork
{
    public const VERSION = '0.1.0';

    /**
     * @property App $app
     */
    private static App $app;

    // TODO Store set up variables within single Config struct
    private static $host;
    private static $port;    
    private static $logger;
    private static $status;
    private static $debug;
    private static $init;
    private static $container;

    private static $config = [];
    private static $jobs = [];

    public function __construct(array $config = null)
    {
        self::$host = $config['host'] ?? '0.0.0.0';
        self::$port = $config['port'] ?? 8080;        
        self::$debug = $config['debug'] ?? false;
        self::$logger = $config['logger'] ?? null;
        self::$container = $config['container'] ?? null;

        if(null !== self::$logger && !(self::$logger instanceof LoggerInterface)) {
            throw new \InvalidArgumentException('ERROR: logger does not belong to the LoggerInterface');
        }
        if(null !== self::$container && !(self::$container instanceof ContainerInterface)) {
            throw new \InvalidArgumentException('ERROR: container does not belong to the ContainerInterface');
        }

        self::$config['workers'] = $config['workers'] ?? (int) shell_exec('nproc') * 4;

        // Some more preparations for Windows hosts
        if (DIRECTORY_SEPARATOR === '\\') {
            if (self::$host === '0.0.0.0') {
                self::$host = '127.0.0.1';
            }
            self::$config['workers'] = 1; // Windows can't hadnle multiple processes with PHP
        }

        // Using Keenwork PSR-7 and PSR-17
        $provider = new Psr17FactoryProvider();
        $provider::setFactories([ CometPsr17Factory::class ]);
        AppFactory::setPsr17FactoryProvider($provider);

        self::$app = AppFactory::create(null, self::$container);
        self::$app->add(new JsonBodyParserMiddleware());
    }

    /**
     * Return config param value or the config at whole
     *
     * @param string $key
     */
    public function getConfig(string $key = null) {
        if (!$key) {
    	    return self::$config;
        } else if (array_key_exists($key, self::$config)) {
    	    return self::$config[$key];
        } else {
    	    return null;
        }
    }

    /**
     * Set up worker initialization code if needed
     *
     * @param callable $init
     */
    public function init (callable $init)
    {
        self::$init = $init;
    }

    /* 	TODO
    	@@@ Error: multi workers init in one php file are not support @@@
		@@@ See http://doc.workerman.net/faq/multi-woker-for-windows.html @@@
	*/
	// TODO Return Job ID
	/*
		Windows Hack
        Timer::add(INTERVAL,
        function() use ($app, $logger) {
            $id = rand(1, $app->getConfig('workers'));
            if ($id == 1) 
                Job::run();            
        });

	*/

    /**
     * Add periodic $job executed every $interval of seconds
     *
     * @param int      $interval
     * @param callable $job
     * @param array    $params
     * @param callable $init
     * @param int      $workers
     * @param string   $name
     */
    public function addJob(int $interval, callable $job, array $params = [], callable $init = null, string $name = '', int $workers = 1) 
    {
    	self::$jobs[] = [ 
    		'interval' => $interval, 
    		'job'      => $job, 
    		'params'   => $params,
    		'init'     => $init,     		 
    		'name'     => $name, 
    		'workers'  => $workers,
    	];
    }

    /**
     * Magic call to any of the Slim App methods like add, addMidleware, handle, run, etc...
     * See the full list of available methods: https://github.com/slimphp/Slim/blob/4.x/Slim/App.php
     *
     * @param string $name
     * @param array $args
     * @return mixed
     */
    public function __call (string $name, array $args)
    {
        return self::$app->$name(...$args);
    }

    /**
     * Handle Workerman request to return Workerman response
     *
     * @param WorkermanRequest $request
     * @return WorkermanResponse
     */
    private static function _handle(WorkermanRequest $request)
    {
    	if ($request->queryString()) {
            parse_str($request->queryString(), $queryParams);
    	} else {
            $queryParams = [];
    	}

        $req = new Request(
            $request->method(),
            $request->uri(),
            $request->header(),
            $request->rawBody(),
            '1.1',
            $_SERVER,
            $request->cookie(),
            $request->file(),
            $queryParams
        );

        $ret = self::$app->handle($req);

        $headers = $ret->getHeaders();

        if (!isset($headers['Server'])) {
            $headers['Server'] = 'Keenwork v' . self::VERSION;
        }

        if (!isset($headers['Content-Type'])) {
            $headers['Content-Type'] = 'text/plain; charset=utf-8';
        }

        return new WorkermanResponse(
            $ret->getStatusCode(),
            $headers,
            $ret->getBody()
        );
    }

    /**
     * Run Keenwork server
     */
    public function run()
    {
        // Write worker output to log file if exists
        if (self::$logger) {
            foreach(self::$logger->getHandlers() as $handler) {
                if ($handler->getUrl()) {
                    Worker::$stdoutFile = $handler->getUrl();
                    break;
                }
            }
        }

        // Init HTTP workers
        $worker = new Worker('http://' . self::$host . ':' . self::$port);
        $worker->count = self::$config['workers'];
        $worker->name = 'Keenwork v' . self::VERSION;

        if (self::$init) {
            $worker->onWorkerStart = self::$init;
        }
//
//        // TODO Add timers to the single main worker for Windows hosts!
//        // FIXME We should use real free random port not fixed 65432
//        // Init JOB workers
//        foreach (self::$jobs as $job) {
//	        $w = new Worker('text://' . self::$host . ':' . 65432);
//    	    $w->count = $job['workers'];
//        	$w->name = 'Keenwork v' . self::VERSION .' [job] ' . $job['name'];
//        	$w->onWorkerStart = function() use ($job) {
//      	        if (self::$init)
//					call_user_func(self::$init);
//            	Timer::add($job['interval'], $job['job']);
//        	};
//        }
//
//        // Main Loop
//        $worker->onMessage = static function($connection, WorkermanRequest $request)
//        {
//            try {
//                $response = self::_handle($request);
//                $connection->send($response);
//            } catch(HttpNotFoundException $error) {
//                $connection->send(new WorkermanResponse(404));
//            } catch(\Throwable $error) {
//                if (self::$debug) {
//                    echo "\n[ERR] " . $error->getFile() . ':' . $error->getLine() . ' >> ' . $error->getMessage();
//                }
//                if (self::$logger) {
//                    self::$logger->error($error->getFile() . ':' . $error->getLine() . ' >> ' . $error->getMessage());
//                }
//                $connection->send(new WorkermanResponse(500));
//            }
//        };
//
//       	// Suppress Workerman startup message
//        global $argv;
//        $argv[] = '-q';
//
//        // Write Keenwork startup message to log file and show on screen
//        $jobsInfo = count(self::$jobs) ? ' / ' . count(self::$jobs) . ' jobs' : '';
//      	$hello = $worker->name . ' [' . self::$config['workers'] . ' workers' . $jobsInfo . '] ready on http://' . self::$host . ':' . self::$port;
//       	if (self::$logger) {
//            self::$logger->info($hello);
//       	}
//
//        if (DIRECTORY_SEPARATOR === '\\') {
//            echo "\n-------------------------------------------------------------------------";
//            echo "\nServer               Listen                              Workers   Status";
//            echo "\n-------------------------------------------------------------------------\n";
//        } else {
//            echo $hello . "\n";
//        }

        Worker::runAll();
    }
}
