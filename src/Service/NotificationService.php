<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Order;

class NotificationService
{
    /** @var list<string> */
    private array $outbox = [];

    public function orderConfirmation(Order $order): void
    {
        $this->outbox[] = sprintf(
            "To: %s\nYour order #%d of %d items: %.2f %s",
            $order->customer->email,
            $order->id,
            count($order->lines()),
            $order->total()->cents / 100,
            $order->total()->currency,
        );
    }

    public function sent(): int
    {
        return count($this->outbox);
    }
}
