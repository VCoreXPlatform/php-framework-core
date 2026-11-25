<?php

declare(strict_types=1);

namespace Core;

use Core\Config\Config;
use Core\DB\Database;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\Router\Router;
use Core\Support\Logger;
use InvalidArgumentException;
use Throwable;

final readonly class Application
{
    public function __construct(
        public Config $config,
        public Container $container,
        public Router $router,
        private Logger $logger,
        private bool $debug,
    ) {
    }

    public static function boot(string $root): self
    {
        $config = Config::load($root);
        $env = $config->get('app.env');
        if (!in_array($env, ['production', 'development'], true)) {
            throw new InvalidArgumentException('APP_ENV must be production or development');
        }
        $container = new Container();
        $router = new Router();
        $logger = new Logger($root . '/storage/logs/app.log');
        $container->instance(Config::class, $config);
        $container->instance(Logger::class, $logger);
        $container->instance(Router::class, $router);
        $container->singleton(Database::class, static fn (): Database => Database::fromConfig($config));
        $services = $root . '/bootstrap/services.php';
        if (is_file($services)) {
            $registerServices = require $services;
            $registerServices($container, $root, $config);
        }

        foreach (['web', 'api'] as $file) {
            $register = require $root . "/routes/{$file}.php";
            $register($router, $root);
        }
        return new self($config, $container, $router, $logger, $env === 'development' && $config->get('app.debug'));
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request, $this->container);
        } catch (HttpException $error) {
            return Response::json(['error' => $error->getMessage()], $error->status, $error->headers);
        } catch (Throwable $error) {
            $this->logger->exception($error);
            $body = ['error' => 'Internal Server Error'];
            if ($this->debug) {
                $body['detail'] = $error->getMessage();
            }
            return Response::json($body, 500);
        }
    }
}
