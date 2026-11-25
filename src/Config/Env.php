<?php

declare(strict_types=1);

namespace Core\Config;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\PutenvAdapter;
use Dotenv\Repository\RepositoryBuilder;
use InvalidArgumentException;

final class Env
{
    public static function load(string $root): void
    {
        $repository = RepositoryBuilder::createWithDefaultAdapters()
            ->addReader(PutenvAdapter::class)
            ->immutable()
            ->make();
        Dotenv::create($repository, $root)->safeLoad();
    }

    public static function get(string $name, string $default): string
    {
        $processValue = getenv($name);
        return $processValue !== false ? $processValue : ($_ENV[$name] ?? $default);
    }

    public static function bool(string $name, bool $default): bool
    {
        return filter_var(self::get($name, $default ? 'true' : 'false'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            ?? throw new InvalidArgumentException("Invalid boolean environment variable: {$name}");
    }
}
