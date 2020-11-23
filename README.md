# Keenwork

## Keenwork = Guzzle + SlimPHP + Workerman

Comet gets all superpowers from Guzzle, Slim and Workerman components as well as adds its own magic.

[Guzzle](https://github.com/guzzle) is a set of PHP components to work with HTTP/1.1 and HTTP/2 services.

[Slim](https://github.com/slimphp/Slim) is a micro-framework that helps write web applications and APIs based on modern PSR standards.

[Workerman](https://github.com/walkor/Workerman) is an asynchronous event-driven framework to build fast and scalable network applications. 

Keenwork allows you natively use all the methods of Slim: http://www.slimframework.com/docs/v4/

## Basics

### Installation

It is recommended that you use [Composer](https://getcomposer.org/) to install Comet.

```bash
$ composer require snegprog/comet-sneg
```

This will install framework itself and all required dependencies. Comet requires PHP 7.1 or newer.

### Hello Comet

Create single app.php file at project root folder with content:

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new Keenwork\Keenman();

$app->get('/hello', 
    function ($request, $response) {              
        return $response
            ->with("Hello, Keenwork!");
});

$app->run();
```

Start it from command line:

```bash
$ php app.php start
```

Then open browser and type in default address http://localhost:8080 - you'll see hello from Comet!

### Simple JSON Response

Let's start Comet server listening on custom host:port and returning JSON payload.

```php
<?php
require_once __DIR__ . '/vendor/autoload.php';

$app = new Comet\Keenman([
    'host' => '127.0.0.1',
    'port' => 8080,
]);

$app->get('/json', 
    function ($request, $response) {        
        $data = [ "message" => "Hello, Keenwork!" ];
        return $response
            ->with($data);
});

$app->run();
```

Start browser or Postman and see the JSON resonse from GET http://127.0.0.1:8080

## Advanced Topics

### PSR-4 and Autoloading

Before you proceed with complex examples, be sure that your composer.json contains "autoload" section like this:

```bash
{
    "require": {
        "snegprog/comet-sneg": "^0.6",
    },
    "autoload": {
        "psr-4": { "App\\": "src/" }
    }
}
```    

If not, you should add the section mentioned above and update all vendor packages and autoload logic by command:

```bash
$ composer update
```    

### Controllers

Create src/Controllers/SimpleController.php:

```php
<?php
declare(strict_types=1);

namespace App\Controllers;

use Comet\Request;
use Comet\Response;

class SimpleController
{    
    private static $counter = 0;

    public function getCounter(Request $request, Response $response, $args)
    {
        $response->getBody()->write(self::$counter);  
        return $response->withStatus(200);
    }

    public function setCounter(Request $request, Response $response, $args)    
    {        
        $body = (string) $request->getBody();
        $json = json_decode($body);
        if (!$json) {
            return $response->withStatus(500);
        }  
        self::$counter = $json->counter;
        return $response;        
    }
}  
```    

Then create Comet server app.php at project root folder:

```php
<?php
declare(strict_types=1);

use Comet\Keenman;
use App\Controllers\SimpleController;

require_once __DIR__ . '/vendor/autoload.php';

$app = new Keenman([
    'host' => 'localhost',
    'port' => 8080,    
]);

$app->setBasePath("/api/v1"); 

$app->get('/counter',
    'App\Controllers\SimpleController:getCounter');

$app->post('/counter',    
    'App\Controllers\SimpleController:setCounter');

$app->run();
```

Now you are ready to get counter value with API GET endpoint. And pay attention to '/api/v1' prefix of URL:

GET http://localhost:8080/api/v1/counter

You can change counter sending JSON request for POST method:

POST http://localhost:8080/api/v1/counter with body { "counter": 100 } and 'application/json' header.

Any call with malformed body will be replied with HTTP 500 code, as defined in controller.

## Deployment

### Debugging and Logging

Comet allows you to debug application showing errors and warnings on the screen console. When you move service to the production it better to use file logs instead. Code snippet below shows you how to enable on-the-screen debug and logging with popular Monolog library: 

```php
<?php
declare(strict_types=1);

use Comet\Keenman;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

require_once __DIR__ . '/vendor/autoload.php';

$formatter = new LineFormatter("\n%datetime% >> %channel%:%level_name% >> %message%", "Y-m-d H:i:s");
$stream = new StreamHandler(__DIR__ . '/log/app.log', Logger::INFO);
$stream->setFormatter($formatter);
$logger = new Logger('app');
$logger->pushHandler($stream);

$app = new Keenman([
    'debug' => true,
    'logger' => $logger,
]);

$app->run();
```

### Nginx

If you would like to use Nginx as reverse proxy or load balancer for your Comet app, insert into nginx.conf these lines:

```php
http {
 
    upstream app {
        server http://path.to.your.app:port;
    }
  
    server {
        listen 80;
         location / {
            proxy_pass         http://app;
            proxy_redirect     off;
        }
    }
}    
```
