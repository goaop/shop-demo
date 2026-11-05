<?php

declare(strict_types=1);

/**
 * Boots the autoloader and the aspect kernel. The mode comes from ?mode= (web) or APP_MODE (CLI, cache warm-up):
 *  - baseline: no aspect kernel at all, the plain application (aspects do not run)
 *  - debug:    'debug' => true, every class goes through the weaving stream filter with freshness checks
 *  - prod:     'debug' => false, woven classes are served from the cache through composer's class map
 *  - prebuilt: 'debug' => false + Features::PREBUILT_CACHE, the cache is built by cache:warmup:aop and trusted
 *  - micro:    production kernel with the empty advices of the per-call micro-benchmark only (bench/run-micro.php)
 */

use App\Aop\AppAspectKernel;
use App\Benchmark\MicroBenchmarkKernel;
use Go\Aop\Features;

$autoloader = require __DIR__ . '/../vendor/autoload.php';

$mode = $_GET['mode'] ?? (getenv('APP_MODE') ?: 'prod');
if (!in_array($mode, ['baseline', 'debug', 'prod', 'prebuilt', 'micro'], true)) {
    throw new InvalidArgumentException("Unknown mode $mode");
}
define('APP_MODE', $mode);

if ($mode !== 'baseline') {
    $cacheDir = getenv('APP_AOP_CACHE_DIR') ?: dirname(__DIR__) . '/var/cache/' . $mode;
    // The kernel creates the cache directory itself, its parent has to exist
    is_dir(dirname($cacheDir)) || mkdir(dirname($cacheDir), 0777, true);

    $kernel = $mode === 'micro' ? MicroBenchmarkKernel::getInstance() : AppAspectKernel::getInstance();
    $kernel->init([
        'debug'        => $mode === 'debug',
        'appDir'       => dirname(__DIR__),
        'cacheDir'     => $cacheDir,
        'includePaths' => [dirname(__DIR__) . '/src'],
        'features'     => $mode === 'prebuilt' ? Features::PREBUILT_CACHE : 0,
    ]);
}

return $autoloader;
