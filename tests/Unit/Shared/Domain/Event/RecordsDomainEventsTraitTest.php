<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Shared\Domain\Event;

use Alcor\Tests\Fixtures\DummyDomainEvent;
use Alcor\Tests\Fixtures\DummyEventRecordingAggregate;
use PHPUnit\Framework\TestCase;

final class RecordsDomainEventsTraitTest extends TestCase
{
    public function testPullDomainEventsReturnsRecordedEventsInOrder(): void
    {
        $aggregate = new DummyEventRecordingAggregate();
        $first = new DummyDomainEvent(new \DateTimeImmutable());
        $second = new DummyDomainEvent(new \DateTimeImmutable());

        $aggregate->recordForTest($first);
        $aggregate->recordForTest($second);

        self::assertSame([$first, $second], $aggregate->pullDomainEvents());
    }

    public function testPullDomainEventsClearsTheBuffer(): void
    {
        $aggregate = new DummyEventRecordingAggregate();
        $aggregate->recordForTest(new DummyDomainEvent(new \DateTimeImmutable()));

        $aggregate->pullDomainEvents();

        self::assertSame([], $aggregate->pullDomainEvents());
    }
}
