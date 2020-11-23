<?php
declare(strict_types=1);

namespace Keenwork\Factory;

class CometPsr17Factory extends \Slim\Factory\Psr17\Psr17Factory
{
    protected static $responseFactoryClass = 'Keenwork\Factory\ResponseFactory';
    protected static $streamFactoryClass = 'Keenwork\Factory\StreamFactory';
    protected static $serverRequestCreatorClass = 'Keenwork\Request';
    protected static $serverRequestCreatorMethod = 'fromGlobals';
}
