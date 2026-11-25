<?php

declare(strict_types=1);

namespace Core\Testing;

use Core\Application;
use Core\Http\Request;

final readonly class HttpClient
{
    public function __construct(private Application $application)
    {
    }

    public function getJson(string $path, array $query = [], array $headers = []): TestResponse
    {
        return $this->request('GET', $path, null, $query, $headers);
    }

    public function postJson(string $path, array $data, array $headers = []): TestResponse
    {
        return $this->request('POST', $path, $data, [], $headers);
    }

    public function request(
        string $method,
        string $path,
        ?array $data = null,
        array $query = [],
        array $headers = [],
    ): TestResponse {
        $headers = array_change_key_case($headers) + ['accept' => 'application/json'];
        $body = '';
        if ($data !== null) {
            $headers['content-type'] = 'application/json';
            $body = json_encode((object) $data, JSON_THROW_ON_ERROR);
        }
        $request = new Request(strtoupper($method), $path, $query, $headers, $body);
        return new TestResponse($this->application->handle($request));
    }
}
