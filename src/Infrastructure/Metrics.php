<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class Metrics
{
    /** @var array<string, array{int, int}> */
    private array $timers = [];

    public function record(string $name, int $nanoseconds): void
    {
        $timer               = $this->timers[$name] ?? [0, 0];
        $this->timers[$name] = [$timer[0] + 1, $timer[1] + $nanoseconds];
    }

    public function calls(): int
    {
        return array_sum(array_column($this->timers, 0));
    }
}
