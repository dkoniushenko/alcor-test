<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Shared\Infrastructure\Event;

use Alcor\Shared\Infrastructure\Event\ConsoleEventDispatcher;
use Alcor\Tests\Fixtures\DummyDomainEvent;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ConsoleEventDispatcherTest extends TestCase
{
    public function testDispatchEchoesTheEventNameAndOccurredAt(): void
    {
        // Given
        $dispatcher = new ConsoleEventDispatcher();
        $event = new DummyDomainEvent(new \DateTimeImmutable('2026-01-01T12:30:00+00:00'));

        // When
        ob_start();
        $dispatcher->dispatch($event);
        $output = ob_get_clean();
        \assert(\is_string($output));

        // Then
        self::assertSame("[event] DummyDomainEvent occurred at 2026-01-01T12:30:00+00:00\n", $output);
    }
}
