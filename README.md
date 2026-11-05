# Go! AOP shop demo

A small shop application built with [Go! AOP](https://github.com/goaop/framework) 4: cross-cutting concerns
(logging, caching, transactions, monitoring, auditing, security) live in six aspects instead of being scattered over the
services. The same application doubles as a reproducible benchmark of the framework's modes: debug, production and
prebuilt cache, cold, warm and hot, with OPcache file cache, preloading and a production-style Docker image.

Measured numbers: [RESULTS.md](RESULTS.md). What they mean and how to tune a real application:
[docs/performance.md](https://github.com/goaop/framework/blob/master/docs/performance.md) of the framework.

```bash
composer install
composer serve                       # http://127.0.0.1:8080/?mode=prod
APP_MODE=prod php public/index.php / # one request from the CLI
```

## The application

| Part                    | Where | Woven |
|-------------------------|-------|:-----:|
| Domain                  | `src/Domain/`: products, customers, orders, order lines, money, enums | `Product` (property advice) |
| Repositories            | `src/Repository/`: in-memory products, customers, orders | yes |
| Services                | `src/Service/`: catalog, pricing, discounts, tax, inventory, payment, notifications, reports | yes |
| HTTP                    | `src/Http/`: request, response, router, checkout and modules controllers | no |
| Infrastructure          | `src/Infrastructure/`: in-memory logger, cache, transactions, metrics, events | no |
| Generated modules       | `src/Module/` (`bin/generate.php [modules=40]`, run on `composer install`): per module a service and a repository (woven), a DTO, a validator and an event (not woven) | partly |
| Library code            | `lib/` (generated with the modules): stands in for `vendor/`, outside the include paths | no |

The infrastructure keeps everything in memory, so the numbers show the cost of the framework, not of I/O.

### Aspects

| Aspect               | Advice | Pointcut |
|----------------------|--------|----------|
| `LoggingAspect`      | `#[Before]` | `@execution(App\Attribute\Loggable)` |
| `CachingAspect`      | `#[Around]` | `@execution(App\Attribute\Cacheable)` |
| `TransactionAspect`  | `#[Around]` | `@execution(App\Attribute\Transactional)` |
| `MonitoringAspect`   | `#[Around]` + `#[AfterThrowing]` | a named `#[Pointcut]` over the repositories, `execution(public App\Service\*->*(*))` |
| `StockAuditAspect`   | `#[Around]` on a property (native property hooks) | `access(public App\Domain\Product->stock)` |
| `SecurityAspect`     | `#[Before]` | `execution(public App\Module\**\*Service->handle(*))` |

They are registered lazily in `src/Aop/AppAspectKernel.php`: an aspect is created on the first call of one of its
advices.

One request to `/` places 50 orders of 5 lines, builds a sales report and calls every module once: ~2,600 intercepted
method calls and ~950 intercepted property accesses. The response is JSON with the business result, the counters of
the aspects, timings, peak memory, the number of included files and OPcache statistics.

### Modes

`config/bootstrap.php` initializes the kernel; the mode comes from `?mode=` or `APP_MODE`:

| Mode       | Kernel |
|------------|--------|
| `baseline` | none: the plain application, the aspects do not run |
| `debug`    | `'debug' => true`: changed classes and aspects are re-woven, XDebug breakpoints work in the original files |
| `prod`     | `'debug' => false`: woven classes come from the cache through Composer's class map |
| `prebuilt` | `'debug' => false` + `Features::PREBUILT_CACHE`: the cache is built by `cache:warmup:aop` and trusted completely |
| `micro`    | production kernel with empty advices only, for the per-call benchmark (`src/Benchmark/`) |

Every mode has its own cache directory below `var/cache/`. Build the prebuilt one with:

```bash
APP_MODE=prebuilt composer warmup
```

## Production-style Docker image

```bash
docker build -t shop-demo .
docker run --rm -p 8080:8080 --read-only --tmpfs /tmp shop-demo
curl 'http://127.0.0.1:8080/?mode=prebuilt'
```

The [Dockerfile](Dockerfile) builds the AOP cache (`cache:warmup:aop` with one worker per CPU) and primes the OPcache
file cache ([docker/opcache-prime.php](docker/opcache-prime.php)) at build time, then runs with `PREBUILT_CACHE`,
`opcache.validate_timestamps=0` and preloading ([config/preload.php](config/preload.php),
[docker/opcache.ini](docker/opcache.ini)) on a read-only root file system. A new container serves its first request in
~15 ms. The image uses PHP's built-in server to stay self-contained; production setups use PHP-FPM with the same
`php.ini` settings.

## Benchmarks

```bash
bash bench/run-all.sh    # ~15 minutes, writes results/
```

Requirements: PHP 8.4+ with OPcache, and for the profiler runs Xdebug installed but not enabled in `php.ini` (the
scripts load it with `-d zend_extension=xdebug` only where they need it). Every run uses `opcache.jit=disable` (the
tracing JIT of PHP 8.5.11/8.5.12 has a bug of its own) and `opcache.file_update_protection=0`, so files woven a moment
before are cached at once.

| Script                       | Measures |
|------------------------------|----------|
| `bench/run-wallclock.php`    | every mode × cold / warm / hot through the built-in web server, and the cache warm-up |
| `bench/run-startup.php`      | the first request of a fresh server with a primed file cache and/or preloading |
| `bench/run-cli.php`          | one process per run: OPcache off, on, with a primed file cache |
| `bench/run-autoloader.php`   | Composer PSR-4, `--optimize-autoloader`, `--classmap-authoritative` |
| `bench/run-micro.php`        | nanoseconds per intercepted call with empty advices |
| `bench/run-profiles.php`     | Xdebug profiler runs (`xdebug.mode=profile`) of every mode and state |
| `bench/analyze-profile.php`  | cachegrind analyzer without KCachegrind: time by subsystem, top functions, file-system calls |

```bash
php bench/analyze-profile.php results/profiles/prod-hot/cachegrind.out.*.gz --top=30
php bench/analyze-profile.php --index results/profiles/index.json    # all scenarios side by side
```

The [CI workflow](.github/workflows/ci.yml) runs every mode on PHP 8.4 and 8.5 and serves requests from the Docker
image on each push. Run it manually (*Actions → CI → Run workflow*) to get the full benchmark as the job summary and
the `benchmark-results` artifact.

## License

MIT, see [LICENSE](LICENSE).
