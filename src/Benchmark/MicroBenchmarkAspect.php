<?php

declare(strict_types=1);

namespace App\Benchmark;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;
use Go\Lang\Attribute\Before;

/**
 * Empty advices: measures the framework's own cost per intercepted call (bench/run-micro.php)
 */
final class MicroBenchmarkAspect implements Aspect
{
    #[Before('execution(public App\Benchmark\Calculator->withBefore(*))')]
    public function before(MethodInvocation $invocation): void {}

    #[Around('execution(public App\Benchmark\Calculator->withAround|withThreeArounds|withOptional(*)) || execution(public App\Benchmark\Calculator::staticWithAround(*))')]
    public function around(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }

    #[Around('execution(public App\Benchmark\Calculator->withThreeArounds(*))')]
    public function second(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }

    #[Around('execution(public App\Benchmark\Calculator->withThreeArounds(*))')]
    public function third(MethodInvocation $invocation): mixed
    {
        return $invocation->proceed();
    }

    #[Around('access(public App\Benchmark\Calculator->watched)')]
    public function field(FieldAccess $access): mixed
    {
        return $access->proceed();
    }
}
