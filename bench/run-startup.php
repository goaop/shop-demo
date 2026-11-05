<?php

declare(strict_types=1);

/**
 * "Pod start": the first requests of a freshly started server (empty OPcache shared memory) on a prebuilt cache,
 * with the start-up helpers a container image can ship:
 *  - opcache.file_cache primed at build time (compiled scripts on disk, loaded instead of compiled)
 *  - opcache.preload of the woven classes (config/preload.php), linked once at server start
 *
 * Usage: php bench/run-startup.php [samples=7]
 */

require __DIR__ . '/lib.php';

$samples   = (int) ($argv[1] ?? 7);
$fileCache = APP_ROOT . '/var/opcache-file';
$preload   = ['opcache.preload=' . APP_ROOT . '/config/preload.php', 'opcache.preload_user=' . (get_current_user() ?: 'root')];
$variants  = [
    'prebuilt'                          => [],
    'prebuilt + file_cache (primed)'    => ['opcache.file_cache=' . $fileCache],
    'prebuilt + preload'                => $preload,
    'prebuilt + preload + file_cache'   => [...$preload, 'opcache.file_cache=' . $fileCache],
    'baseline (no AOP)'                 => [],
    'baseline + file_cache (primed)'    => ['opcache.file_cache=' . $fileCache],
];

warmUpCache('prebuilt');
$results = [];
foreach ($variants as $name => $ini) {
    $mode = str_starts_with($name, 'baseline') ? 'baseline' : 'prebuilt';
    removeTree($fileCache);
    mkdir($fileCache, 0777, true);
    if (in_array('opcache.file_cache=' . $fileCache, $ini, true)) {
        // build step: one request fills the file cache, the server is thrown away
        $server = startServer($ini, ['APP_MODE' => $mode]);
        request('mode=' . $mode);
        stopServer($server);
    }
    $first = [];
    $hot   = [];
    for ($s = 0; $s < $samples; $s++) {
        $server  = startServer($ini, ['APP_MODE' => $mode]);
        $first[] = request('mode=' . $mode);
        for ($i = 0; $i < 40; $i++) {
            $hot[] = request('mode=' . $mode)['app']['timing']['total_ms'];
        }
        stopServer($server);
    }
    $row = [
        'first_client_ms' => median(array_column($first, 'client_ms')),
        'first_total_ms'  => median(array_map(static fn($r) => $r['app']['timing']['total_ms'], $first)),
        'hot_total_ms'    => median($hot),
        'included_files'  => $first[0]['app']['included_files'],
    ];
    $results[$name] = $row;
    printf("%-34s first request %7.2f ms (client %7.2f)   hot %5.2f ms   files %d\n", $name, $row['first_total_ms'], $row['first_client_ms'], $row['hot_total_ms'], $row['included_files']);
}
file_put_contents(APP_ROOT . '/results/startup.json', json_encode($results, JSON_PRETTY_PRINT) . "\n");
