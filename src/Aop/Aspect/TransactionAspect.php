<?php

declare(strict_types=1);

namespace App\Aop\Aspect;

use App\Infrastructure\TransactionManager;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

final class TransactionAspect implements Aspect
{
    public function __construct(
        private readonly TransactionManager $transactions,
    ) {}

    #[Around('@execution(App\Attribute\Transactional)')]
    public function aroundTransactional(MethodInvocation $invocation): mixed
    {
        $this->transactions->begin();
        try {
            $result = $invocation->proceed();
            $this->transactions->commit();

            return $result;
        } catch (\Throwable $exception) {
            $this->transactions->rollback();
            throw $exception;
        }
    }
}
