# Results

Reference run of `bash bench/run-all.sh` on goaop/framework 4.0-dev (`4ee4842`): PHP 8.5.12 NTS (Ubuntu packages),
4 vCPUs (cloud sandbox), OPcache on, `opcache.jit=disable`, `opcache.file_update_protection=0`, Xdebug 3.5.3 for the
profiles only. Machines differ: compare the modes with each other, not the absolute numbers with your hardware.

One request (`/`): 50 orders × 5 lines + a sales report + one call into each of the 40 modules. That makes 2,616 method
joinpoint invocations, 5,919 `proceed()` calls and 950 intercepted property accesses, with 226 joinpoints initialized.

## Web requests

The built-in web server (one worker, OPcache shared memory kept between requests), median script time:

| Mode                                | cold   | warm  | hot         | peak memory (hot) | files included |
|-------------------------------------|-------:|------:|------------:|------------------:|---------------:|
| `baseline` (no AOP, aspects do not run) | —  | 36 ms | **2.1 ms**  | 0.7 MB            | 376            |
| `debug`                             | 516 ms | 63 ms | **13.0 ms** | 2.2 MB            | 522            |
| `prod`                              | 517 ms | 62 ms | **5.5 ms**  | 1.3 MB            | 517            |
| `prebuilt`                          | built by the warm-up | 59 ms | **5.4 ms** | 1.3 MB | 517        |

- **cold**: empty AOP cache and OPcache: the request weaves every class it loads (196 cache files written).
- **warm**: AOP cache present, OPcache empty: a freshly started server or pod.
- **hot**: both caches filled: the steady state.

`cache:warmup:aop`: 0.4–0.5 s for the 247 files of this app. With `bin/generate.php 400` (2,047 files): 2.24 s with
1 worker, 1.36 s with 2, **1.06 s with 4**, 1.35 s with 8 workers on 4 CPUs.

## Cost per intercepted call

Empty advices (`micro` mode), so only the framework's own work is measured:

| Call                                           | overhead |
|------------------------------------------------|---------:|
| Method or property without advice in a woven class | 0    |
| Method with one `#[Before]` or `#[Around]`     | ~330 ns  |
| Method with three `#[Around]`                  | ~475 ns  |
| Static method with one `#[Around]`             | ~340 ns  |
| Property read / write with one `#[Around]`     | ~220 / ~265 ns |

## Pod start

First request of a freshly started server on a prebuilt cache:

| Variant                                    | first request | hot     | files included |
|--------------------------------------------|--------------:|--------:|---------------:|
| `prebuilt`                                 | 71 ms         | 5.2 ms  | 517            |
| + `opcache.file_cache` primed at build time | 19 ms        | 5.4 ms  | 517            |
| + `opcache.preload` (`config/preload.php`) | 24 ms         | 4.9 ms  | 174            |
| + both                                     | **10 ms**     | 4.9 ms  | 174            |
| `baseline` + primed file cache             | 9 ms          | 2.2 ms  | 376            |

The Docker image of this repository combines both: 13–15 ms for the first request of a new container.

## CLI processes

One process per run, script time:

| Mode       | OPcache off | OPcache on (memory only) | `opcache.file_cache` primed |
|------------|------------:|-------------------------:|----------------------------:|
| `baseline` | 19 ms       | 34 ms                    | 10 ms                       |
| `debug`    | 36 ms       | 66 ms                    | 27 ms                       |
| `prod`     | 30 ms       | 69 ms                    | 22 ms                       |
| `prebuilt` | 33 ms       | 69 ms                    | 21 ms                       |

## Composer autoloader

Hot request:

| Autoloader                  | `baseline` | `prod`  | `prebuilt` |
|-----------------------------|-----------:|--------:|-----------:|
| PSR-4                       | 2.3 ms     | 6.7 ms  | 5.5 ms     |
| `--optimize-autoloader`     | 1.6 ms     | 5.0 ms  | 5.2 ms     |
| `--classmap-authoritative`  | 1.9 ms     | 4.6 ms  | 4.8 ms     |

## Where the time goes

Share of the profiled time (Xdebug profiler; it slows call-heavy code down the most, so interception is overstated):

| Bucket                                   | `prod` hot | `debug` hot | `prod` cold |
|------------------------------------------|-----------:|------------:|------------:|
| runtime interception (joinpoints, interceptors) | 44 % | 33 %        | 2 %         |
| application code                         | 31 %       | 24 %        | 1.5 %       |
| advice bodies                            | 10 %       | 7 %         | 0.5 %       |
| class loading + cache metadata           | 6 %        | 20 %        | 7 %         |
| Composer autoloader                      | 5 %        | 4 %         | 1 %         |
| kernel + container                       | 1 %        | 2 %         | 0.2 %       |
| parser + parser-reflection               | 0          | 0           | 64 %        |
| transformers + proxy generators          | 0          | 0.3 %       | 11 %        |
| aspect loading + advice matching         | 0          | 0           | 7 %         |

File-system calls of one hot request:

| Call                               | `prebuilt` | `prod` | `debug`   | `baseline` |
|------------------------------------|-----------:|-------:|----------:|-----------:|
| `filemtime` / `filesize`           | 0          | 0      | 323 / 239 | 0          |
| `file_get_contents`                | 0          | 0      | 94        | 0          |
| `file_exists` (Composer PSR-4)     | 307        | 307    | 412       | 357        |
