<?php

declare(strict_types=1);

namespace Alcor\Shared\Domain\Event;

trait RecordsDomainEventsTrait
{
    /** @var list<AbstractDomainEvent> */
    private array $domainEvents = [];

    /** @return list<AbstractDomainEvent> */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];

        return $events;
    }

    protected function record(AbstractDomainEvent $event): void
    {
        $this->domainEvents[] = $event;
    }
}
