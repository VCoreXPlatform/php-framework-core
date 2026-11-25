<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\Http\Response;
use RuntimeException;

final readonly class TestResponse
{
    public function __construct(public Response $response)
    {
    }

    public function assertStatus(int $expected): self
    {
        if ($this->response->status !== $expected) {
            throw new RuntimeException("Expected status {$expected}, received {$this->response->status}");
        }
        return $this;
    }

    public function assertJson(mixed $expected): self
    {
        if ($this->json() !== $expected) {
            throw new RuntimeException('Response JSON differs from expected data');
        }
        return $this;
    }

    public function json(): mixed
    {
        return json_decode($this->response->body, true, 512, JSON_THROW_ON_ERROR);
    }
}
