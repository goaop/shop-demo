<?php

declare(strict_types=1);

namespace App\Benchmark;

use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

/**
 * Kernel of the per-call micro-benchmark (APP_MODE=micro): only the empty advices of MicroBenchmarkAspect
 */
final class MicroBenchmarkKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        $container->addLazyService(MicroBenchmarkAspect::class, static fn() => new MicroBenchmarkAspect());
    }
}
