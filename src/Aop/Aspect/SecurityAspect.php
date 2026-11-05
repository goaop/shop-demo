<?php

declare(strict_types=1);

namespace App\Aop\Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;

final class SecurityAspect implements Aspect
{
    public int $checks = 0;

    #[Before('execution(public App\Module\**\*Service->handle(*))')]
    public function checkAccess(MethodInvocation $invocation): void
    {
        ++$this->checks;
        if ($invocation->getArguments() === []) {
            throw new \LogicException('Anonymous access denied');
        }
    }
}
