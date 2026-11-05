<?php

declare(strict_types=1);

namespace App;

use App\Http\Controller\CheckoutController;
use App\Http\Controller\ModulesController;
use App\Http\Request;
use App\Http\Response;
use App\Http\Router;
use App\Infrastructure\ServiceLocator;
use App\Repository\CustomerRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Service\CatalogService;
use App\Service\DiscountService;
use App\Service\InventoryService;
use App\Service\NotificationService;
use App\Service\OrderPlaced;
use App\Service\OrderService;
use App\Service\PaymentService;
use App\Service\PricingService;
use App\Service\ReportService;
use App\Service\TaxCalculator;

final class Application
{
    private Router $router;

    public function __construct()
    {
        $services      = ServiceLocator::get();
        $products      = new ProductRepository();
        $orders        = new OrderRepository();
        $catalog       = new CatalogService($products);
        $notifications = new NotificationService();
        $services->events->listen(
            OrderPlaced::class,
            static fn(object $event) => $event instanceof OrderPlaced ? $notifications->orderConfirmation($event->order) : null,
        );

        $checkout = new CheckoutController(
            new CustomerRepository(),
            $catalog,
            new OrderService(
                $catalog,
                new PricingService(new DiscountService()),
                new TaxCalculator(),
                new InventoryService($products),
                new PaymentService(),
                $orders,
                $services->events,
            ),
            new ReportService($orders),
        );
        $modules = new ModulesController();

        $this->router = new Router();
        $this->router->add('/checkout', $checkout(...));
        $this->router->add('/modules', $modules(...));
        $this->router->add('/', static fn(Request $request): Response => new Response([
            'checkout' => $checkout($request)->data,
            'modules'  => $modules($request)->data,
        ]));
    }

    public function handle(Request $request): Response
    {
        $response = $this->router->dispatch($request);
        $services = ServiceLocator::get();

        return new Response($response->data + [
            'stats' => [
                'log_records'  => $services->logger->count(),
                'cache_hits'   => $services->cache->hits,
                'commits'      => $services->transactions->commits,
                'repo_calls'   => $services->metrics->calls(),
                'events'       => $services->events->dispatched,
            ],
        ], $response->status);
    }
}
