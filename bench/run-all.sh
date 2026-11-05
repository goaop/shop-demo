#!/usr/bin/env bash
# Runs every benchmark of the app and writes results/*.txt|json|md. Takes ~15 minutes.
# Requirements: PHP 8.4+ with OPcache, the Xdebug extension installed but NOT enabled in php.ini
# (the profiler run loads it with -d zend_extension=xdebug), `composer install` done in this directory.
set -euo pipefail
cd "$(dirname "$0")/.."
if php -m | grep -qi '^xdebug$'; then
    echo "Xdebug is enabled in php.ini: disable it (it slows every run down), the profiler runs load it themselves" >&2
    exit 1
fi
mkdir -p results var/cache
php bench/run-wallclock.php 7 50 | tee results/wallclock.txt
php bench/run-cli.php 15        | tee results/cli.txt
php bench/run-startup.php 7     | tee results/startup.txt
php bench/run-autoloader.php 300 | tee results/autoloader.txt
php bench/run-micro.php 500000  | tee results/micro.txt
if php -d zend_extension=xdebug -m 2>/dev/null | grep -qi '^xdebug$'; then
    php bench/run-profiles.php > /dev/null
    php bench/analyze-profile.php --index results/profiles/index.json | tee results/profiles-comparison.md
    for scenario in prod-cold debug-hot prod-hot prebuilt-hot; do
        php bench/analyze-profile.php "$(php -r 'echo json_decode(file_get_contents("results/profiles/index.json"), true)[$argv[1]];' "$scenario")" > "results/profile-$scenario.md"
    done
else
    echo "Xdebug is not installed: profiler runs skipped"
fi
