<?php

declare(strict_types=1);

namespace Core\Support;

use Throwable;

final readonly class Logger
{
    public function __construct(private string $file)
    {
    }

    public function exception(Throwable $error): void
    {
        $entry = json_encode(
            [
                'time' => gmdate(DATE_ATOM),
                'type' => $error::class,
                'message' => $error->getMessage(),
                'file' => $error->getFile(),
                'line' => $error->getLine(),
            ],
            JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE,
        ) . PHP_EOL;
        if (@file_put_contents($this->file, $entry, FILE_APPEND | LOCK_EX) === false) {
            error_log($entry);
        }
    }
}
