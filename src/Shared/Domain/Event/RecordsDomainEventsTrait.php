<?php

declare(strict_types=1);

namespace Alcor\Shared\Domain\Event;

trait RecordsDomainEventsTrait
{
    /** @var list<AbstractDomainEvent> */
    private array $domainEvents = [];

    protected function record(AbstractDomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }

    /** @return list<AbstractDomainEvent> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }
}
