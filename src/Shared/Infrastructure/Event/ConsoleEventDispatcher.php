<?php

declare(strict_types=1);

namespace Alcor\Shared\Infrastructure\Event;

use Alcor\Shared\Application\Event\EventDispatcherInterface;
use Alcor\Shared\Domain\Event\AbstractDomainEvent;

final class ConsoleEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(AbstractDomainEvent $event): void
    {
        echo \sprintf(
            "[event] %s occurred at %s\n",
            new \ReflectionClass($event)->getShortName(),
            $event->occurredAt->format(\DateTimeInterface::ATOM),
        );
    }
}
