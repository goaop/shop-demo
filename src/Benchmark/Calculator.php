<?php

declare(strict_types=1);

namespace App\Benchmark;

/**
 * Targets of the per-call micro-benchmark (bench/run-micro.php): one method per interception shape
 */
class Calculator
{
    public int $counter = 0;

    public int $watched = 0;

    public function plain(int $a, int $b): int
    {
        return $a + $b;
    }

    public function withBefore(int $a, int $b): int
    {
        return $a + $b;
    }

    public function withAround(int $a, int $b): int
    {
        return $a + $b;
    }

    public function withThreeArounds(int $a, int $b): int
    {
        return $a + $b;
    }

    public function withOptional(int $a, int $b = 1): int
    {
        return $a + $b;
    }

    public static function staticWithAround(int $a, int $b): int
    {
        return $a + $b;
    }
}
