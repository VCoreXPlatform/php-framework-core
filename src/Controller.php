<?php

declare(strict_types=1);

namespace Core;

use Core\Http\Response;

abstract class Controller
{
    protected function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }
}
