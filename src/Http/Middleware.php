<?php

declare(strict_types=1);

namespace Core\Http;

use Closure;

interface Middleware
{
    public function process(Request $request, Closure $next): Response;
}
