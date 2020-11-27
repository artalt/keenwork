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
use DI\Container;
use Workerman\Lib\Timer;

Worker::$pidFile = __DIR__ . '/workerman.pid';

$app = new Keenwork();
$app->initHttp([
    'debug' => true,
    'container' => new Container(),
    'host' => '127.0.0.1',
    'port' => 8080,
    'workers' => ((int) shell_exec('nproc')*2),
]);

/**
 * Initialization at startup for each worker
 */
$app->init(function () {
});

/**
 * example: Base path urn
 */
$app->setBasePath("/v1");

/**
 * example: Route GET Request to Controller
 */
$app->get('/controller', 'Keenwork\Controller\MyController:myMethod');

/**
 * example: Simple route GET Request
 */
$app->get('/simple', function (Request $request, Response $response): ResponseInterface
{
    return $response
        ->with("Hello, Keenwork!");
});

$wsWorker = new Worker('websocket://0.0.0.0:2346');
$wsWorker->count = ((int) shell_exec('nproc')*2);

// Emitted when new connection come
$wsWorker->onConnect = function ($connection) {
    $connection->send('connected');

    Timer::add(1, function () use ($connection) {
        $connection->send('timer');
    });
};

// Emitted when data received
$wsWorker->onMessage = function ($connection, $data) {
    // Send hello $data
    $connection->send('Hello ' . $data);
};

// Emitted when connection closed
$wsWorker->onClose = function ($connection) {
    echo "Connection closed\n";
};

$app->run();
