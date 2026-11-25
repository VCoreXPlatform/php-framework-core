<?php

declare(strict_types=1);

namespace Core\Http;

final readonly class Response
{
    public function __construct(public string $body = '', public int $status = 200, public array $headers = [])
    {
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        return new self(
            json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            $status,
            $headers + ['Content-Type' => 'application/json; charset=utf-8'],
        );
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->status, [$name => $value] + $this->headers);
    }

    public function send(string $method = 'GET'): void
    {
        http_response_code($this->status);
        foreach ($this->headers + ['X-Content-Type-Options' => 'nosniff'] as $name => $value) {
            header($name . ': ' . $value);
        }
        if ($method !== 'HEAD' && $this->status >= 200 && !in_array($this->status, [204, 205, 304], true)) {
            echo $this->body;
        }
    }
}
