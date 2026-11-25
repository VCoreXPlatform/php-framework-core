<?php

declare(strict_types=1);

namespace Core\Config;

use InvalidArgumentException;
use RuntimeException;

final readonly class Config
{
    public function __construct(private array $values)
    {
    }

    public static function load(string $root): self
    {
        Env::load($root);
        $values = [];
        foreach (glob($root . '/config/*.php') ?: [] as $file) {
            $value = require $file;
            if (!is_array($value)) {
                throw new RuntimeException('Config files must return arrays');
            }
            $values[pathinfo($file, PATHINFO_FILENAME)] = $value;
        }
        return new self($values);
    }

    public function get(string $key): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                throw new InvalidArgumentException("Unknown configuration key: {$key}");
            }
            $value = $value[$segment];
        }
        return $value;
    }
}
