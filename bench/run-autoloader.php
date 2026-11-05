<?php

declare(strict_types=1);

/**
 * Hot and warm requests with the three Composer autoloader flavours: PSR-4 (default), optimized class map (-o),
 * authoritative class map (-a). Restores the default autoloader at the end.
 *
 * Usage: php bench/run-autoloader.php [requests=300]
 */

require __DIR__ . '/lib.php';

$requests = (int) ($argv[1] ?? 300);
$flavours = ['psr-4' => '', 'optimized (-o)' => '--optimize', 'authoritative (-a)' => '--classmap-authoritative'];
$results  = [];
foreach ($flavours as $flavour => $flag) {
    exec('cd ' . escapeshellarg(APP_ROOT) . ' && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -q ' . $flag);
    foreach (['baseline', 'prod', 'prebuilt'] as $mode) {
        $warm = [];
        $hot  = [];
        for ($s = 0; $s < 5; $s++) {
            $server = startServer([]);
            $warm[] = request('mode=' . $mode)['app']['timing']['total_ms'];
            for ($i = 0; $i < intdiv($requests, 5); $i++) {
                $hot[] = request('mode=' . $mode)['app']['timing']['total_ms'];
            }
            stopServer($server);
        }
        $results[$flavour][$mode] = ['warm_ms_p50' => median($warm), 'hot_ms_p50' => median($hot)];
        printf("%-20s %-9s warm %7.2f ms   hot %6.2f ms\n", $flavour, $mode, median($warm), median($hot));
    }
}
exec('cd ' . escapeshellarg(APP_ROOT) . ' && COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload -q');
file_put_contents(APP_ROOT . '/results/autoloader.json', json_encode($results, JSON_PRETTY_PRINT) . "\n");
