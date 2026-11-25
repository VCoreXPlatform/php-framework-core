<?php

declare(strict_types=1);

namespace Core\Router;

use Closure;
use Core\Container;
use Core\Http\HttpException;
use Core\Http\Middleware;
use Core\Http\Request;
use Core\Http\Response;
use InvalidArgumentException;
use LogicException;

final class Router
{
    private array $routes = [];
    private string $prefix = '';
    private array $middleware = [];
    private ?Closure $fallback = null;

    public function add(string $method, string $path, Closure|array $handler, array $middleware = []): void
    {
        $method = strtoupper($method);
        $path = $this->normalize($this->prefix . '/' . trim($path, '/'));
        $key = "{$method} {$path}";
        if (isset($this->routes[$key])) {
            throw new InvalidArgumentException("Duplicate route: {$key}");
        }
        $this->routes[$key] = new Route($method, $path, $handler, [...$this->middleware, ...$middleware]);
    }

    public function group(string $prefix, array $middleware, Closure $register): void
    {
        $previousPrefix = $this->prefix;
        $previousMiddleware = $this->middleware;
        $this->prefix = rtrim($this->normalize($this->prefix . '/' . trim($prefix, '/')), '/');
        $this->middleware = [...$this->middleware, ...$middleware];
        try {
            $register($this);
        } finally {
            $this->prefix = $previousPrefix;
            $this->middleware = $previousMiddleware;
        }
    }

    public function fallback(Closure $handler): void
    {
        $this->fallback = $handler;
    }

    public function dispatch(Request $request, Container $container): Response
    {
        $path = $this->normalize($request->path);
        $allowed = [];
        $headFallback = null;
        foreach ($this->routes as $route) {
            $parameters = $route->parameters($path);
            if ($parameters === null) {
                continue;
            }
            if ($route->method === $request->method) {
                return $this->run($route, $parameters, $request, $container);
            }
            $allowed[] = $route->method;
            if ($route->method === 'GET') {
                $allowed[] = 'HEAD';
                $headFallback ??= [$route, $parameters];
            }
        }
        if ($request->method === 'HEAD' && $headFallback !== null) {
            return $this->run($headFallback[0], $headFallback[1], $request, $container);
        }
        if ($allowed !== []) {
            throw new HttpException(405, 'Method Not Allowed', ['Allow' => implode(', ', array_unique($allowed))]);
        }
        if ($this->fallback !== null) {
            return ($this->fallback)($request);
        }
        throw new HttpException(404, 'Not Found');
    }

    private function run(Route $route, array $parameters, Request $request, Container $container): Response
    {
        $next = static fn (Request $current): Response =>
            $container->call($route->handler, [Request::class => $current] + $parameters);

        foreach (array_reverse($route->middleware) as $class) {
            $downstream = $next;
            $next = static function (Request $current) use ($container, $class, $downstream): Response {
                $middleware = $container->get($class);
                if (!$middleware instanceof Middleware) {
                    throw new LogicException("Invalid middleware: {$class}");
                }
                return $middleware->process($current, $downstream);
            };
        }
        return $next($request);
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
