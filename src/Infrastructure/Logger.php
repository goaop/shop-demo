<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * In-memory logger: keeps the advice bodies cheap, so the benchmark measures the framework, not I/O
 */
final class Logger
{
    /** @var list<string> */
    private array $records = [];

    public function log(string $level, string $message): void
    {
        $this->records[] = $level . ': ' . $message;
    }

    public function count(): int
    {
        return count($this->records);
    }
}
