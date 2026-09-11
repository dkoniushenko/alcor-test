<?php

declare(strict_types=1);

namespace Alcor\Tests\Fixtures;

use Alcor\Shared\Domain\Event\AbstractDomainEvent;
use Alcor\Shared\Domain\Event\RecordsDomainEventsTrait;

// Not a real aggregate — exists only so RecordsDomainEventsTrait's own tests
// have something concrete to record events on (record() is protected, so it
// needs a using class to call it from).
final class DummyEventRecordingAggregate
{
    use RecordsDomainEventsTrait;

    public function recordForTest(AbstractDomainEvent $event): void
    {
        $this->record($event);
    }
}
