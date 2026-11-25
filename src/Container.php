<?php

declare(strict_types=1);

namespace Core;

use Closure;
use ReflectionClass;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

final class Container
{
    private array $instances = [];
    private array $factories = [];
    private array $resolving = [];

    public function instance(string $id, object $instance): void
    {
        $this->instances[$id] = $instance;
        unset($this->factories[$id]);
    }

    public function singleton(string $id, Closure $factory): void
    {
        $this->factories[$id] = $factory;
        unset($this->instances[$id]);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }
        if (isset($this->resolving[$id])) {
            throw new RuntimeException("Circular dependency: {$id}");
        }

        $this->resolving[$id] = true;
        try {
            if (isset($this->factories[$id])) {
                return $this->instances[$id] = ($this->factories[$id])($this);
            }
            $class = new ReflectionClass($id);
            if (!$class->isInstantiable()) {
                throw new RuntimeException("No binding for {$id}");
            }
            $parameters = $class->getConstructor()?->getParameters() ?? [];
            return $class->newInstanceArgs($this->arguments($parameters, []));
        } finally {
            unset($this->resolving[$id]);
        }
    }

    public function call(Closure|array $handler, array $named = []): mixed
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $reflection = new ReflectionMethod($class, $method);
            if (!$reflection->isPublic()) {
                throw new RuntimeException('Route handlers must be public');
            }
            $target = $reflection->isStatic() ? null : (is_object($class) ? $class : $this->get($class));
            return $reflection->invokeArgs($target, $this->arguments($reflection->getParameters(), $named));
        }
        $reflection = new ReflectionFunction($handler);
        return $handler(...$this->arguments($reflection->getParameters(), $named));
    }

    private function arguments(array $parameters, array $named): array
    {
        $arguments = [];
        foreach ($parameters as $parameter) {
            if (!$parameter->isVariadic()) {
                $arguments[] = $this->argument($parameter, $named);
            }
        }
        return $arguments;
    }

    private function argument(ReflectionParameter $parameter, array $named): mixed
    {
        $name = $parameter->getName();
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $named[$type->getName()] ?? $this->get($type->getName());
        }
        if (array_key_exists($name, $named)) {
            return $named[$name];
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        throw new RuntimeException("Cannot resolve parameter \${$name}");
    }
}
