<?php

declare(strict_types=1);

/**
 * Xdebug profiler (xdebug.mode=profile) runs of every mode × {cold, warm, hot} through the built-in web server.
 * Writes results/profiles/<mode>-<state>/cachegrind.out.* (the analyzed one is listed in results/profiles/index.json).
 *
 * The profiler slows PHP down several times and distorts cheap calls the most: use the profiles for the
 * distribution of the time, the wall-clock runs (run-wallclock.php) for absolute numbers.
 *
 * Usage: php bench/run-profiles.php
 */

require __DIR__ . '/lib.php';

$profileRoot = APP_ROOT . '/results/profiles';
removeTree($profileRoot);
@mkdir($profileRoot, 0777, true);

$xdebug = static fn(string $dir): array => [
    'zend_extension=xdebug',
    'xdebug.mode=profile',
    'xdebug.start_with_request=yes',
    'xdebug.output_dir=' . $dir,
    'xdebug.profiler_output_name=cachegrind.out.%u',
];

$newest = static function (string $dir): string {
    $files = glob($dir . '/cachegrind.out.*') ?: [];
    sort($files); // %u = microtime: lexical order is the request order

    return (string) end($files);
};

$index = [];
foreach (['baseline', 'debug', 'prod', 'prebuilt'] as $mode) {
    $query = 'mode=' . $mode;
    if ($mode === 'debug' || $mode === 'prod') {
        removeTree(cacheDir($mode));
        $dir = "$profileRoot/$mode-cold";
        mkdir($dir);
        $server = startServer($xdebug($dir));
        request($query);
        stopServer($server);
        $index["$mode-cold"] = $newest($dir);
    } elseif ($mode === 'prebuilt') {
        warmUpCache('prebuilt');
    }

    $dir = "$profileRoot/$mode-warm";
    mkdir($dir);
    $server = startServer($xdebug($dir));
    request($query);
    $index["$mode-warm"] = $newest($dir);
    for ($i = 0; $i < 5; $i++) {
        request($query);
    }
    stopServer($server);
    // hot: the last of the requests of the same server
    mkdir("$profileRoot/$mode-hot");
    $hot = $newest($dir);
    rename($hot, "$profileRoot/$mode-hot/" . basename($hot));
    $index["$mode-hot"] = "$profileRoot/$mode-hot/" . basename($hot);
    fprintf(STDERR, "%s profiled\n", $mode);
}

file_put_contents("$profileRoot/index.json", json_encode($index, JSON_PRETTY_PRINT) . "\n");
echo json_encode($index, JSON_PRETTY_PRINT), "\n";
