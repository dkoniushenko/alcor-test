<?php

declare(strict_types=1);

namespace Alcor\Shared\Domain\Event;

abstract readonly class AbstractDomainEvent
{
    public function __construct(
        public \DateTimeImmutable $occurredAt,
    ) {
    }
}
