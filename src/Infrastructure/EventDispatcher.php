<?php

declare(strict_types=1);

namespace App\Infrastructure;

final class EventDispatcher
{
    /** @var array<string, list<callable(object): void>> */
    private array $listeners = [];

    public int $dispatched = 0;

    public function listen(string $event, callable $listener): void
    {
        $this->listeners[$event][] = $listener;
    }

    public function dispatch(object $event): void
    {
        ++$this->dispatched;
        foreach ($this->listeners[$event::class] ?? [] as $listener) {
            $listener($event);
        }
    }
}
