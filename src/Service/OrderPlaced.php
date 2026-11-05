<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Order;

final readonly class OrderPlaced
{
    public function __construct(
        public Order $order,
    ) {}
}
