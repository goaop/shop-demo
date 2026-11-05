<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Http\Request;
use App\Http\Response;
use App\Module\ModuleRegistry;

/**
 * Touches every generated module once: loads the classes of a larger application (services, repositories, DTOs,
 * validators and the library classes they use), like a real request does through its container
 */
final class ModulesController
{
    public function __invoke(Request $request): Response
    {
        $checksum = 0;
        foreach (ModuleRegistry::services() as $serviceClass) {
            $service   = new $serviceClass();
            $checksum += $service->handle($request->int('seed', 42));
        }

        return new Response(['modules' => count(ModuleRegistry::services()), 'checksum' => $checksum]);
    }
}
