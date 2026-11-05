<?php

declare(strict_types=1);

namespace App\Aop;

use App\Aop\Aspect\CachingAspect;
use App\Aop\Aspect\LoggingAspect;
use App\Aop\Aspect\MonitoringAspect;
use App\Aop\Aspect\SecurityAspect;
use App\Aop\Aspect\StockAuditAspect;
use App\Aop\Aspect\TransactionAspect;
use App\Infrastructure\ServiceLocator;
use Go\Core\AspectContainer;
use Go\Core\AspectKernel;

final class AppAspectKernel extends AspectKernel
{
    protected function configureAop(AspectContainer $container): void
    {
        // Lazy services: an aspect is created on the first call of one of its advices
        $container->addLazyService(LoggingAspect::class, static fn() => new LoggingAspect(ServiceLocator::get()->logger));
        $container->addLazyService(CachingAspect::class, static fn() => new CachingAspect(ServiceLocator::get()->cache));
        $container->addLazyService(TransactionAspect::class, static fn() => new TransactionAspect(ServiceLocator::get()->transactions));
        $container->addLazyService(
            MonitoringAspect::class,
            static fn() => new MonitoringAspect(ServiceLocator::get()->metrics, ServiceLocator::get()->logger),
        );
        $container->addLazyService(StockAuditAspect::class, static fn() => new StockAuditAspect(ServiceLocator::get()->logger));
        $container->addLazyService(SecurityAspect::class, static fn() => new SecurityAspect());
    }
}
