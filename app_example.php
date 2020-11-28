#!/usr/bin/env php
<?php
/**
 * Example app
 */

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Keenwork\Request;
use Keenwork\Response;
use Workerman\Worker;
use Keenwork\Keenwork;
use Psr\Http\Message\ResponseInterface;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Workerman\Lib\Timer;

Worker::$pidFile = __DIR__ . '/workerman.pid';

$formatter = new LineFormatter("\n%datetime% >> %channel%:%level_name% >> %message%", "Y-m-d H:i:s");
$stream = new StreamHandler(__DIR__ . '/log/app.log', Logger::INFO);
$stream->setFormatter($formatter);
$logger = new Logger('app');
$logger->pushHandler($stream);

$app = new Keenwork($logger);

/**
 * Data initialization for http server
 */
$app->initHttp([
    'debug' => true,
    'host' => '127.0.0.1',
    'port' => 8080,
    'workers' => ((int) shell_exec('nproc')*2),
]);

/**
 * Initialization at startup for each worker
 */
$app->callableAtStartHttp(function () {
});

/**
 * example: Base path urn
 */
$app->getSlim()->setBasePath("/v1");

/**
 * example: Route GET Request to Controller
 */
$app->getSlim()->get('/controller', 'Keenwork\Controller\MyController:myMethod');

/**
 * example: Simple route GET Request
 */
$app->getSlim()->get('/simple', function (Request $request, Response $response) use ($app): ResponseInterface {
    return $response
        ->with($app->getConfigsHttp());
});

//$wsWorker = new Worker('websocket://0.0.0.0:2346');
//$wsWorker->count = ((int) shell_exec('nproc')*2);
//
//// Emitted when new connection come
//$wsWorker->onConnect = function ($connection) {
//    $connection->send('connected');
//
//    Timer::add(1, function () use ($connection) {
//        $connection->send('timer');
//    });
//};
//
//// Emitted when data received
//$wsWorker->onMessage = function ($connection, $data) {
//    // Send hello $data
//    $connection->send('Hello ' . $data);
//};
//
//// Emitted when connection closed
//$wsWorker->onClose = function ($connection) {
//    echo "Connection closed\n";
//};

Keenwork::runAll($app);
