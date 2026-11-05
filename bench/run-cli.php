<?php

declare(strict_types=1);

/**
 * CLI matrix: one process per run (cron jobs, queue workers that restart, `php` commands in containers).
 * Every CLI process starts with an empty OPcache shared memory, so it is "warm" at best; opcache.file_cache keeps the
 * compiled scripts on disk between processes (a K8s image can ship it pre-filled).
 *
 * Usage: php bench/run-cli.php [runs=15]
 */

require __DIR__ . '/lib.php';

$runs    = (int) ($argv[1] ?? 15);
$modes   = ['baseline', 'debug', 'prod', 'prebuilt'];
$fileDir = APP_ROOT . '/var/opcache-file';
$variants = [
    'opcache off'                => ['opcache.enable_cli=0'],
    'opcache on (shm only)'      => ['opcache.enable_cli=1'],
    'opcache.file_cache (primed)' => ['opcache.enable_cli=1', 'opcache.file_cache=' . $fileDir, 'opcache.file_cache_consistency_checks=0'],
    'file_cache + validate_timestamps=0' => ['opcache.enable_cli=1', 'opcache.file_cache=' . $fileDir, 'opcache.file_cache_consistency_checks=0', 'opcache.validate_timestamps=0'],
];

// caches of every mode exist
foreach (['debug', 'prod'] as $mode) {
    removeTree(cacheDir($mode));
    exec(sprintf('APP_MODE=%s %s -d opcache.file_update_protection=0 %s / > /dev/null', $mode, PHP_BINARY, escapeshellarg(APP_ROOT . '/public/index.php')));
}
warmUpCache('prebuilt');

$results = [];
foreach ($modes as $mode) {
    foreach ($variants as $variant => $ini) {
        removeTree($fileDir);
        @mkdir($fileDir, 0777, true);
        $command = 'APP_MODE=' . $mode . ' ' . PHP_BINARY;
        foreach (array_merge(['opcache.jit=disable', 'opcache.file_update_protection=0', 'memory_limit=512M'], $ini) as $setting) {
            $command .= ' -d ' . escapeshellarg($setting);
        }
        $command .= ' ' . escapeshellarg(APP_ROOT . '/public/index.php') . ' /';
        exec($command . ' > /dev/null'); // primes the file cache
        $process = [];
        $inside  = [];
        for ($i = 0; $i < $runs; $i++) {
            $start = hrtime(true);
            $out   = shell_exec($command);
            $process[] = (hrtime(true) - $start) / 1e6;
            $json      = json_decode((string) $out, true);
            $inside[]  = is_array($json) ? $json['timing']['total_ms'] : NAN;
        }
        $results[$mode][$variant] = ['process_ms_p50' => median($process), 'script_ms_p50' => median($inside)];
        printf("%-9s %-36s process %7.2f ms   script %7.2f ms\n", $mode, $variant, median($process), median($inside));
    }
}
@mkdir(APP_ROOT . '/results', 0777, true);
file_put_contents(APP_ROOT . '/results/cli.json', json_encode($results, JSON_PRETTY_PRINT) . "\n");
