<?php

declare(strict_types=1);

/**
 * Wall-clock matrix: mode × {cold, warm, hot} through the built-in web server (one worker, OPcache in shared memory),
 * without Xdebug. Writes results/wallclock.json and prints a table.
 *
 *  cold - empty AOP cache, fresh server (empty OPcache): first request weaves everything
 *         (prebuilt: there is no runtime cold state, the cache is built by cache:warmup:aop, timed separately)
 *  warm - AOP cache present, fresh server (empty OPcache): every file is compiled once
 *  hot  - AOP cache present, OPcache filled: steady state of a production worker
 *
 * Usage: php bench/run-wallclock.php [samples=7] [hotRequests=50]
 */

require __DIR__ . '/lib.php';

$samples     = (int) ($argv[1] ?? 7);
$hotRequests = (int) ($argv[2] ?? 50);
$modes       = ['baseline', 'debug', 'prod', 'prebuilt'];
$variants    = [
    // name => extra ini of the hot runs
    'default'               => [],
    'validate_timestamps=0' => ['opcache.validate_timestamps=0'],
];

@mkdir(APP_ROOT . '/results', 0777, true);
@mkdir(APP_ROOT . '/var/cache', 0777, true);
$results = ['php' => PHP_VERSION, 'samples' => $samples, 'hot_requests' => $hotRequests, 'modes' => []];

$warmupTimes = ['workers=1' => [], 'workers=4' => []];
for ($i = 0; $i < $samples; $i++) {
    $warmupTimes['workers=1'][] = warmUpCache('prebuilt', 1);
    $warmupTimes['workers=4'][] = warmUpCache('prebuilt', 4);
}
$results['warmup_command_ms'] = array_map(median(...), $warmupTimes);
warmUpCache('prebuilt');

foreach ($modes as $mode) {
    $query = 'mode=' . $mode;
    $data  = ['cold' => [], 'warm' => [], 'hot' => []];
    for ($i = 0; $i < $samples; $i++) {
        if ($mode === 'debug' || $mode === 'prod') {
            removeTree(cacheDir($mode));
            $server = startServer([]);
            $data['cold'][] = request($query);
            stopServer($server);
        }

        $server = startServer([]);
        $data['warm'][] = request($query);
        for ($r = 0; $r < $hotRequests; $r++) {
            $data['hot'][] = request($query);
        }
        stopServer($server);
    }

    foreach (array_slice($variants, 1, null, true) as $variant => $ini) {
        $server = startServer($ini);
        request($query);
        for ($r = 0; $r < $hotRequests * $samples; $r++) {
            $data['hot:' . $variant][] = request($query);
        }
        stopServer($server);
    }

    foreach ($data as $state => $runs) {
        if ($runs === []) {
            continue;
        }
        $client = array_column($runs, 'client_ms');
        $timing = array_column($runs, 'app');
        $results['modes'][$mode][$state] = [
            'n'              => count($runs),
            'client_ms_p50'  => median($client),
            'client_ms_p90'  => percentile($client, 0.9),
            'boot_ms_p50'    => median(array_map(static fn($t) => $t['timing']['boot_ms'], $timing)),
            'handle_ms_p50'  => median(array_map(static fn($t) => $t['timing']['handle_ms'], $timing)),
            'total_ms_p50'   => median(array_map(static fn($t) => $t['timing']['total_ms'], $timing)),
            'memory_peak_kb' => max(array_map(static fn($t) => $t['memory_peak_kb'], $timing)),
            'included_files' => max(array_map(static fn($t) => $t['included_files'], $timing)),
        ];
    }
    fprintf(STDERR, "%s done\n", $mode);
}

file_put_contents(APP_ROOT . '/results/wallclock.json', json_encode($results, JSON_PRETTY_PRINT) . "\n");

printf("PHP %s, %d samples, %d hot requests per sample, JIT off, Xdebug off\n\n", PHP_VERSION, $samples, $hotRequests);
printf("cache:warmup:aop: %.0f ms (1 worker), %.0f ms (4 workers)\n\n", $results['warmup_command_ms']['workers=1'], $results['warmup_command_ms']['workers=4']);
printf("%-10s %-28s %10s %10s %10s %10s %10s %8s %7s\n", 'mode', 'state', 'client p50', 'client p90', 'boot p50', 'handle p50', 'total p50', 'mem KB', 'files');
foreach ($results['modes'] as $mode => $states) {
    foreach ($states as $state => $row) {
        printf(
            "%-10s %-28s %10.2f %10.2f %10.2f %10.2f %10.2f %8d %7d\n",
            $mode, $state, $row['client_ms_p50'], $row['client_ms_p90'], $row['boot_ms_p50'], $row['handle_ms_p50'],
            $row['total_ms_p50'], $row['memory_peak_kb'], $row['included_files'],
        );
    }
}
