<?php

declare(strict_types=1);

namespace App\Aop\Aspect;

use App\Infrastructure\Logger;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Before;

final class LoggingAspect implements Aspect
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    #[Before('@execution(App\Attribute\Loggable)')]
    public function beforeLoggable(MethodInvocation $invocation): void
    {
        $this->logger->log(
            'info',
            $invocation->getMethod()->class . '->' . $invocation->getMethod()->name
                . ' with ' . count($invocation->getArguments()) . ' arguments',
        );
    }
}
