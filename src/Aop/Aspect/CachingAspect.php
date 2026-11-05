<?php

declare(strict_types=1);

namespace App\Aop\Aspect;

use App\Infrastructure\ArrayCache;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Attribute\Around;

final class CachingAspect implements Aspect
{
    public function __construct(
        private readonly ArrayCache $cache,
    ) {}

    #[Around('@execution(App\Attribute\Cacheable)')]
    public function aroundCacheable(MethodInvocation $invocation): mixed
    {
        $key = $invocation->getMethod()->class . '::' . $invocation->getMethod()->name;
        foreach ($invocation->getArguments() as $argument) {
            $key .= ':' . (is_scalar($argument) ? $argument : get_debug_type($argument));
        }
        if ($this->cache->has($key)) {
            return $this->cache->get($key);
        }
        $result = $invocation->proceed();
        $this->cache->set($key, $result);

        return $result;
    }
}
