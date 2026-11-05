<?php

declare(strict_types=1);

namespace App\Http;

final class Router
{
    /** @var array<string, \Closure(Request): Response> */
    private array $routes = [];

    /** @param \Closure(Request): Response $handler */
    public function add(string $path, \Closure $handler): void
    {
        $this->routes[$path] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $handler = $this->routes[$request->path] ?? null;

        return $handler !== null ? $handler($request) : new Response(['error' => 'Not found'], 404);
    }
}
