<?php

declare(strict_types=1);

use App\Application;
use App\Http\Request;

$start = hrtime(true);

require __DIR__ . '/../config/bootstrap.php';

$booted = hrtime(true);

if (PHP_SAPI === 'cli') {
    $path  = $argv[1] ?? '/';
    $query = [];
    parse_str($argv[2] ?? '', $query);
} else {
    $path  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $query = $_GET;
}

$application = new Application();
$response    = $application->handle(new Request($_SERVER['REQUEST_METHOD'] ?? 'GET', $path, $query));
$end         = hrtime(true);

$opcache = function_exists('opcache_get_status') ? opcache_get_status(false) : false;

if (PHP_SAPI !== 'cli') {
    http_response_code($response->status);
    header('Content-Type: application/json');
}
echo json_encode([
    'mode'     => APP_MODE,
    'response' => $response->data,
    'timing'   => [
        'boot_ms'   => ($booted - $start) / 1e6,
        'handle_ms' => ($end - $booted) / 1e6,
        'total_ms'  => ($end - $start) / 1e6,
    ],
    'memory_peak_kb' => intdiv(memory_get_peak_usage(), 1024),
    'included_files' => count(get_included_files()),
    'opcache'        => is_array($opcache) ? [
        'enabled' => $opcache['opcache_enabled'],
        'scripts' => $opcache['opcache_statistics']['num_cached_scripts'] ?? null,
        'hits'    => $opcache['opcache_statistics']['hits'] ?? null,
        'misses'  => $opcache['opcache_statistics']['misses'] ?? null,
    ] : null,
], JSON_PRETTY_PRINT), "\n";
