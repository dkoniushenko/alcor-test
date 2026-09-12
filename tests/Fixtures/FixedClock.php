<?php

declare(strict_types=1);

namespace Alcor\Tests\Fixtures;

use Alcor\Shared\Domain\Clock\ClockInterface;

final readonly class FixedClock implements ClockInterface
{
    public function __construct(
        private \DateTimeImmutable $now,
    ) {}

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }
}
