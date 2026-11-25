<?php

declare(strict_types=1);

namespace Core\Http;

use JsonException;
use stdClass;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $headers = [],
        private string $body = '',
        private array $form = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_') || in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $name = preg_replace('/^HTTP_/', '', $key);
                $headers[strtolower(str_replace('_', '-', $name))] = $value;
            }
        }
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            explode('?', $uri, 2)[0],
            $_GET,
            $headers,
            file_get_contents('php://input', false, null, 0, 1_048_577) ?: '',
            $_POST,
        );
    }

    public function input(): array
    {
        if (strlen($this->body) > 1_048_576) {
            throw new HttpException(413, 'Request body too large');
        }
        $type = strtolower(trim(explode(';', $this->headers['content-type'] ?? '')[0]));
        if ($type === 'application/json') {
            try {
                $decoded = json_decode($this->body, false, 64, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new HttpException(400, 'Malformed JSON');
            }
            if (!$decoded instanceof stdClass) {
                throw new HttpException(400, 'Expected a JSON object');
            }
            return (array) $decoded;
        }
        if ($type === 'application/x-www-form-urlencoded') {
            parse_str($this->body, $data);
            return $this->form ?: $data;
        }
        if ($type === 'multipart/form-data' && $this->method === 'POST') {
            return $this->form;
        }
        if ($this->body !== '') {
            throw new HttpException(415, 'Unsupported media type');
        }
        return [];
    }
}
