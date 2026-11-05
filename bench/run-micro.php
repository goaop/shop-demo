<?php

declare(strict_types=1);

/**
 * Per-call cost of interception: the same calls with the plain class (baseline) and the woven one with empty advices
 * (APP_MODE=micro), JIT off, Xdebug off. The overhead is the framework's own: joinpoint, interceptor chain, call of the
 * original method.
 *
 * Usage: php bench/run-micro.php [iterations=500000]   (runs itself twice, as baseline and as micro)
 */

use App\Benchmark\Calculator;

if (($argv[2] ?? '') === '--child') {
    require __DIR__ . '/../config/bootstrap.php';
    $iterations = (int) $argv[1];
    $calc       = new Calculator();
    $cases      = [
        'plain method (not intercepted)' => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $calc->plain($i, 1); }
        },
        'before advice'                  => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $calc->withBefore($i, 1); }
        },
        'around advice'                  => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $calc->withAround($i, 1); }
        },
        '3 around advices'               => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $calc->withThreeArounds($i, 1); }
        },
        'around, optional argument omitted' => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $calc->withOptional($i); }
        },
        'around, static method'          => static function () use ($iterations): void {
            for ($i = 0; $i < $iterations; $i++) { Calculator::staticWithAround($i, 1); }
        },
        'property read, around advice'   => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $x = $calc->watched; }
        },
        'property write, around advice'  => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $calc->watched = $i; }
        },
        'property read, not intercepted' => static function () use ($calc, $iterations): void {
            for ($i = 0; $i < $iterations; $i++) { $x = $calc->counter; }
        },
    ];
    $result = [];
    foreach ($cases as $name => $case) {
        $case(); // first call creates the joinpoints
        $best = INF;
        for ($round = 0; $round < 5; $round++) {
            $start = hrtime(true);
            $case();
            $best = min($best, (hrtime(true) - $start) / $iterations);
        }
        $result[$name] = $best;
    }
    echo json_encode($result);
    exit;
}

require __DIR__ . '/lib.php';
$iterations = (int) ($argv[1] ?? 500000);
$run = static function (string $mode) use ($iterations): array {
    $command = sprintf(
        'APP_MODE=%s %s -d opcache.enable_cli=1 -d opcache.jit=disable %s %d --child',
        $mode, PHP_BINARY, escapeshellarg(__FILE__), $iterations,
    );

    return json_decode((string) shell_exec($command), true, flags: JSON_THROW_ON_ERROR);
};
removeTree(cacheDir('micro'));
$run('micro'); // weaves Calculator
$baseline = $run('baseline');
$micro    = $run('micro');
printf("%-36s %12s %12s %12s\n", 'case', 'plain ns', 'woven ns', 'overhead ns');
$results = [];
foreach ($micro as $name => $ns) {
    $plain = str_starts_with($name, 'property') ? ($name === 'property read, not intercepted' ? $baseline[$name] : $baseline['property read, not intercepted']) : $baseline['plain method (not intercepted)'];
    if ($name === 'around, static method') {
        $plain = $baseline[$name];
    }
    if ($name === 'property write, around advice') {
        $plain = $baseline[$name];
    }
    $results[$name] = ['plain_ns' => $plain, 'woven_ns' => $ns, 'overhead_ns' => $ns - $plain];
    printf("%-36s %12.1f %12.1f %12.1f\n", $name, $plain, $ns, $ns - $plain);
}
@mkdir(APP_ROOT . '/results', 0777, true);
file_put_contents(APP_ROOT . '/results/micro.json', json_encode($results, JSON_PRETTY_PRINT) . "\n");
