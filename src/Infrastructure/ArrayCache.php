<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class ArrayCache
{
    /** @var array<string, mixed> */
    private array $items = [];

    public int $hits = 0;

    public int $misses = 0;

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }

    public function get(string $key): mixed
    {
        ++$this->hits;

        return $this->items[$key];
    }

    public function set(string $key, mixed $value): void
    {
        ++$this->misses;
        $this->items[$key] = $value;
    }
}
