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
use Rakit\Validation\Validator;

class Keenwork
{
    /**
     * Version Keenwork
     */
    public const VERSION = '0.1.0';

    /**
     * WEB Slim App
     * @var App $slim
     */
    private App $slim;

    /**
     * host web server
     * @var string
     */
    private string $hostHttp;

    /**
     * port web server
     * @var int
     */
    private int $portHttp;

    /**
     * enable|disable debag mode for workerman
     * @var bool
     */
    private bool $debug;

    /**
     * logger PSR-3 for Slim
     * @var LoggerInterface|null
     */
    private ?LoggerInterface $logger;

    /**
     * container PSR-11 for Slim
     * @var ContainerInterface|null
     */
    private ?ContainerInterface $container;

    /**
     * callable starts when starts worker
     * @var callable
     */
    private $init;

    /**
     * Number of workers workerman
     * @var int
     */
    private int $workersHttp;

    //TODO: think
    private static $config = [];
    private static $jobs = [];

    /**
     * Init http server
     * @param array|null $config
     */
    public function initHttp(array $config = []): void
    {
        $validator = new Validator();
        $validation = $validator->make($config, [
            'host' => 'ip',
            'port' => 'integer',
            'workers' => 'integer',
            'debug' => 'boolean',
        ]);
        $validation->validate();
        if ($validation->fails()) {
            $stringErrors = '[';
            foreach($validation->errors()->toArray() as $key => $error) {
                $stringErrors .= ' ' .$key . ' ';
            }
            $stringErrors .= ']';

            throw new \InvalidArgumentException('ERROR: initHttp(): invalid argument(s): ' . $stringErrors . '.');
        }

        $this->setHost($config['host'] ?? '0.0.0.0');
        $this->setPort($config['port'] ?? 8080);
        $this->setDebug($config['debug'] ?? false);
        $this->setWorkers($config['workers'] ?? ((int) shell_exec('nproc')*4));
        $this->setLogger($config['logger'] ?? null);
        $this->setContainer($config['container'] ?? null);

        $provider = new Psr17FactoryProvider();
        $provider::setFactories([CometPsr17Factory::class]);
        AppFactory::setPsr17FactoryProvider($provider);

        $this->setSlim(AppFactory::create(null, $this->container));
        $this->getSlim()->add(new JsonBodyParserMiddleware());
    }

    /**
     * Return config param value or the config at whole
     *
     * @param string $key
     */
    public function getConfigHttp(string $key = null) {
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
        $this->init = $init;
    }

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
        return $this->slim->$name(...$args);
    }

    /**
     * Handle Workerman request to return Workerman response
     *
     * @param WorkermanRequest $request
     * @return WorkermanResponse
     */
    private function _handle(WorkermanRequest $request)
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

        $ret = $this->getSlim()->handle($req);

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
        if (null !== $this->getLogger()) {
            foreach($this->getLogger()->getHandlers() as $handler) {
                if ($handler->getUrl()) {
                    Worker::$stdoutFile = $handler->getUrl();
                    break;
                }
            }
        }

        // Init HTTP workers
        $worker = new Worker('http://' . $this->hostHttp . ':' . $this->portHttp);
        $worker->count = $this->workersHttp;
        $worker->name = 'Keenwork v' . self::VERSION;

        if ($this->init) {
            $worker->onWorkerStart = $this->init;
        }

        // FIXME We should use real free random port not fixed 65432
        // Init JOB workers
//        foreach (self::$jobs as $job) {
//	        $w = new Worker('text://' . $this->host . ':' . 65432);
//    	    $w->count = $job['workers'];
//        	$w->name = 'Keenwork v' . self::VERSION .' [job] ' . $job['name'];
//        	$w->onWorkerStart = function() use ($job) {
//      	        if ($this->init)
//					call_user_func($this->init);
//            	Timer::add($job['interval'], $job['job']);
//        	};
//        }

        //TODO: think
        /** Main Loop */
        $worker->onMessage = function($connection, WorkermanRequest $request)
        {
            try {
                $response = $this->_handle($request);
                $connection->send($response);
            } catch(HttpNotFoundException $error) {
                $connection->send(new WorkermanResponse(404));
            } catch(\Throwable $error) {
                if ($this->isDebug()) {
                    echo "\n[ERR] " . $error->getFile() . ':' . $error->getLine() . ' >> ' . $error->getMessage();
                }
                if (null !== $this->getLogger()) {
                    $this->getLogger()->error($error->getFile() . ':' . $error->getLine() . ' >> ' . $error->getMessage());
                }
                $connection->send(new WorkermanResponse(500));
            }
        };

        Worker::runAll();
    }

    /**
     * @return App
     */
    public function getSlim(): App
    {
        return $this->slim;
    }

    /**
     * @return string
     */
    public function getHostHttp(): string
    {
        return $this->hostHttp;
    }

    /**
     * @return int
     */
    public function getPortHttp(): int
    {
        return $this->portHttp;
    }

    /**
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @return LoggerInterface|null
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * @return ContainerInterface|null
     */
    public function getContainer(): ?ContainerInterface
    {
        return $this->container;
    }

    /**
     * @return mixed
     */
    public function getInit(): callable
    {
        return $this->init;
    }

    /**
     * @return int
     */
    public function getWorkersHttp(): int
    {
        return $this->workersHttp;
    }

    /**
     * @return array
     */
    public static function getConfig(): array
    {
        return self::$config;
    }

    /**
     * @return array
     */
    public static function getJobs(): array
    {
        return self::$jobs;
    }

    /**
     * @param App $slim
     */
    private function setSlim(App $slim): void
    {
        $this->slim = $slim;
    }

    /**
     * @param string $host
     */
    private function setHost(string $host): void
    {
        $this->hostHttp = $host;
    }

    /**
     * @param int $port
     */
    private function setPort(int $port): void
    {
        $this->portHttp = $port;
    }

    /**
     * @param bool $debug
     */
    private function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * @param LoggerInterface|null $logger
     */
    private function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param ContainerInterface|null $container
     */
    private function setContainer(?ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @param mixed $init
     */
    private function setInit($init): void
    {
        $this->init = $init;
    }

    /**
     * @param int $workers
     */
    private function setWorkers(int $workers): void
    {
        $this->workersHttp = $workers;
    }

    /**
     * @param array $config
     */
    private static function setConfig(array $config): void
    {
        self::$config = $config;
    }

    /**
     * @param array $jobs
     */
    private static function setJobs(array $jobs): void
    {
        self::$jobs = $jobs;
    }
}
