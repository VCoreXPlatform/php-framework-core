<?php

declare(strict_types=1);

namespace Core\Router;

use Closure;
use InvalidArgumentException;

final readonly class Route
{
    private string $pattern;

    public function __construct(
        public string $method,
        string $path,
        public Closure|array $handler,
        public array $middleware,
    ) {
        $parts = [];
        $names = [];
        foreach (explode('/', $path) as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/D', $segment, $match)) {
                $name = $match[1];
                if (isset($names[$name])) {
                    throw new InvalidArgumentException("Duplicate route parameter: {$name}");
                }
                $names[$name] = true;
                $parts[] = "(?P<{$name}>[^/]+)";
            } else {
                $parts[] = preg_quote($segment, '~');
            }
        }
        $this->pattern = '~\A' . implode('/', $parts) . '\z~';
    }

    public function parameters(string $path): ?array
    {
        if (!preg_match($this->pattern, $path, $matches)) {
            return null;
        }
        return array_map('rawurldecode', array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
    }
}
