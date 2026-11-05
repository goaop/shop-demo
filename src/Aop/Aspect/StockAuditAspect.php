<?php

declare(strict_types=1);

namespace App\Aop\Aspect;

use App\Infrastructure\Logger;
use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\FieldAccessType;
use Go\Lang\Attribute\Around;

/**
 * Property interception through native property hooks: audits every write of the stock
 */
final class StockAuditAspect implements Aspect
{
    public function __construct(
        private readonly Logger $logger,
    ) {}

    #[Around('access(public App\Domain\Product->stock)')]
    public function auditStock(FieldAccess $access): mixed
    {
        if ($access->getAccessType() === FieldAccessType::Write) {
            $this->logger->log('audit', 'stock := ' . get_debug_type($access->getValueToSet()));
        }

        return $access->proceed();
    }
}
