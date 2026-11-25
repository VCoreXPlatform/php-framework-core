<?php

declare(strict_types=1);

namespace Core\DB;

use InvalidArgumentException;

final class Identifier
{
    public static function validate(string $name): string
    {
        if (!preg_match('/\A[a-z][a-z0-9_]{0,63}\z/', $name)) {
            throw new InvalidArgumentException('Invalid database identifier');
        }
        return $name;
    }
}
