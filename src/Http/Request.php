<?php

declare(strict_types=1);

namespace App\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
    ) {}

    public function int(string $name, int $default): int
    {
        return isset($this->query[$name]) ? (int) $this->query[$name] : $default;
    }
}
