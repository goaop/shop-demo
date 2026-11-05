<?php

declare(strict_types=1);

/**
 * opcache.preload script for a prebuilt AOP cache (experimental, see docs/performance.md): links the woven classes,
 * their proxies and the untransformed application classes into OPcache shared memory at server start.
 *
 * Classes, not constants, are preloaded: AspectKernel::init() still runs on every request (defines AOP_CACHE_DIR,
 * builds the aspect container), the autoloader just never has to load the preloaded classes.
 */

$appDir   = dirname(__DIR__);
$cacheDir = getenv('APP_AOP_CACHE_DIR') ?: $appDir . '/var/cache/' . (getenv('APP_MODE') ?: 'prebuilt');

$loader = require $appDir . '/vendor/autoload.php';
// The woven files include their proxies through this constant
define('AOP_CACHE_DIR', $cacheDir);

$includeMap = include $cacheDir . '/_include.cache';
// Woven classes resolve to the woven trait files of the cache, like AopComposerLoader does in production
$loader->addClassMap($includeMap['map']);

foreach ([...array_keys($includeMap['map']), ...array_keys($includeMap['skip'])] as $class) {
    class_exists($class) || interface_exists($class) || trait_exists($class) || enum_exists($class);
}
