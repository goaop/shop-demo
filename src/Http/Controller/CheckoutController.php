<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Domain\OrderStatus;
use App\Domain\OutOfStock;
use App\Http\Request;
use App\Http\Response;
use App\Repository\CustomerRepository;
use App\Service\CatalogService;
use App\Service\OrderService;
use App\Service\ReportService;

final class CheckoutController
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly CatalogService $catalog,
        private readonly OrderService $orders,
        private readonly ReportService $reports,
    ) {}

    public function __invoke(Request $request): Response
    {
        $orderCount = $request->int('orders', 50);
        $placed     = 0;
        $failed     = 0;
        for ($i = 1; $i <= $orderCount; $i++) {
            $customer = $this->customers->find(1 + $i % 50) ?? throw new \LogicException('No customer');
            // Browsing: a category page, mostly answered by the caching aspect
            $products = $this->catalog->listCategory(['books', 'games', 'music', 'garden', 'kitchen', 'tools'][$i % 6]);
            $items    = [];
            for ($line = 0; $line < 5; $line++) {
                $items[$products[($i * 7 + $line * 3) % count($products)]->id] = 1 + ($i + $line) % 6;
            }
            try {
                $order = $this->orders->placeOrder($customer, $items);
                $order->status === OrderStatus::Paid ? $placed++ : $failed++;
            } catch (OutOfStock) {
                $failed++;
            }
        }

        return new Response(['placed' => $placed, 'declined' => $failed, 'report' => $this->reports->salesReport()]);
    }
}
