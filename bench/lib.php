<?php

declare(strict_types=1);

/**
 * Helpers of the benchmark scripts: built-in web server lifecycle, requests, cache directories, statistics
 */

const APP_ROOT = __DIR__ . '/..';
const PORT     = 8090;

/**
 * Base php.ini overrides of every run: OPcache on, JIT off (PHP 8.5.11/8.5.12 tracing JIT bug),
 * file_update_protection off, no Xdebug
 *
 * @return list<string>
 */
function baseIni(): array
{
    return [
        'opcache.enable=1',
        'opcache.enable_cli=1',
        'opcache.jit=disable',
        // Cache every file at once: by default OPcache skips files modified in the last 2 seconds, which would leave
        // the freshly woven cache files uncached right after weaving. Safe for the AOP cache: it is written with
        // atomic tmp+rename, a half-written file is never visible
        'opcache.file_update_protection=0',
        'opcache.memory_consumption=128',
        'opcache.max_accelerated_files=20000',
        'opcache.interned_strings_buffer=16',
        'memory_limit=512M',
    ];
}

/**
 * @param list<string> $ini
 * @return array{0: resource, 1: array<int, resource>}
 */
function startServer(array $ini, array $env = []): array
{
    // The system php.ini (Xdebug not enabled there) plus the settings below
    $command = [PHP_BINARY];
    foreach (array_merge(baseIni(), $ini) as $setting) {
        $command[] = '-d';
        $command[] = $setting;
    }
    array_push($command, '-S', '127.0.0.1:' . PORT, '-t', APP_ROOT . '/public', APP_ROOT . '/public/index.php');

    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes, APP_ROOT, $env + getenv());
    if (!is_resource($process)) {
        throw new RuntimeException('Can not start the web server');
    }
    for ($i = 0; $i < 200; $i++) {
        $socket = @fsockopen('127.0.0.1', PORT, $errno, $errstr, 0.05);
        if ($socket !== false) {
            fclose($socket);

            return [$process, $pipes];
        }
        usleep(10_000);
    }
    throw new RuntimeException('The web server did not start');
}

/** @param array{0: resource, 1: array<int, resource>} $server */
function stopServer(array $server): void
{
    proc_terminate($server[0]);
    proc_close($server[0]);
    // wait until the port is free again
    for ($i = 0; $i < 200; $i++) {
        $socket = @fsockopen('127.0.0.1', PORT, $errno, $errstr, 0.02);
        if ($socket === false) {
            return;
        }
        fclose($socket);
        usleep(10_000);
    }
}

/**
 * @return array{client_ms: float, app: array<string, mixed>}
 */
function request(string $query): array
{
    $start = hrtime(true);
    $body  = file_get_contents('http://127.0.0.1:' . PORT . '/?' . $query);
    $time  = (hrtime(true) - $start) / 1e6;
    $data  = is_string($body) ? json_decode($body, true) : null;
    if (!is_array($data)) {
        throw new RuntimeException('Bad response: ' . substr((string) $body, 0, 2000));
    }

    return ['client_ms' => $time, 'app' => $data];
}

function cacheDir(string $mode): string
{
    return APP_ROOT . '/var/cache/' . $mode;
}

function removeTree(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) && !is_link($path) ? removeTree($path) : unlink($path);
    }
    rmdir($dir);
}

/**
 * Builds the prebuilt cache with the warm-up command, returns the wall time in ms
 */
function warmUpCache(string $mode, int $workers = 1): float
{
    removeTree(cacheDir($mode));
    $start = hrtime(true);
    $command = sprintf(
        'APP_MODE=%s %s -d opcache.enable_cli=1 -d opcache.jit=disable -d opcache.file_update_protection=0 -d memory_limit=512M %s cache:warmup:aop %s --workers=%d 2>&1',
        escapeshellarg($mode),
        escapeshellarg(PHP_BINARY),
        escapeshellarg(APP_ROOT . '/vendor/bin/aspect'),
        escapeshellarg(APP_ROOT . '/config/bootstrap.php'),
        $workers,
    );
    exec($command, $output, $code);
    $time = (hrtime(true) - $start) / 1e6;
    if ($code !== 0 || !str_contains(implode("\n", $output), ' 0 errors')) {
        throw new RuntimeException("Warm-up failed:\n" . implode("\n", $output));
    }

    return $time;
}

/** @param list<float> $values */
function median(array $values): float
{
    sort($values);
    $count = count($values);
    if ($count === 0) {
        return NAN;
    }
    $middle = intdiv($count, 2);

    return $count % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
}

/** @param list<float> $values */
function percentile(array $values, float $p): float
{
    sort($values);

    return $values[(int) min(count($values) - 1, floor($p * count($values)))];
}
