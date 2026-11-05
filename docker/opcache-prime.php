<?php

declare(strict_types=1);

/**
 * Compiles what the application loads into opcache.file_cache: Composer's class map, Composer's autoloaded files,
 * the AOP cache and the given entry points. Run with -d opcache.enable_cli=1 -d opcache.file_cache_only=1.
 *
 * Usage: php docker/opcache-prime.php <app dir> <AOP cache dir> [entry point files...]
 */

[, $appDir, $aopCacheDir] = $argv;
$files = [
    ...array_values(require "$appDir/vendor/composer/autoload_classmap.php"),
    ...(is_file("$appDir/vendor/composer/autoload_files.php") ? array_values(require "$appDir/vendor/composer/autoload_files.php") : []),
    ...array_slice($argv, 3),
];
$cacheFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($aopCacheDir, FilesystemIterator::SKIP_DOTS));
foreach ($cacheFiles as $file) {
    if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
        $files[] = $file->getPathname();
    }
}
$compiled = 0;
foreach (array_unique($files) as $file) {
    $compiled += (int) opcache_compile_file($file);
}
echo "Compiled $compiled files into opcache.file_cache\n";
