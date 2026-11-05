<?php

declare(strict_types=1);

namespace App\Infrastructure;

/**
 * Shared infrastructure of the request: the aspects and the services take it from here
 */
final class ServiceLocator
{
    private static ?self $instance = null;

    public readonly Logger $logger;

    public readonly ArrayCache $cache;

    public readonly TransactionManager $transactions;

    public readonly Metrics $metrics;

    public readonly EventDispatcher $events;

    private function __construct()
    {
        $this->logger       = new Logger();
        $this->cache        = new ArrayCache();
        $this->transactions = new TransactionManager();
        $this->metrics      = new Metrics();
        $this->events       = new EventDispatcher();
    }

    public static function get(): self
    {
        return self::$instance ??= new self();
    }
}
