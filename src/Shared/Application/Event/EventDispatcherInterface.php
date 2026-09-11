<?php

declare(strict_types=1);

namespace Alcor\Shared\Application\Event;

use Alcor\Shared\Domain\Event\AbstractDomainEvent;

interface EventDispatcherInterface
{
    public function dispatch(AbstractDomainEvent $event): void;
}
