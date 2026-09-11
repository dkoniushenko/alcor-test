<?php

declare(strict_types=1);

namespace Alcor\Shared\Infrastructure\Clock;

use Alcor\Shared\Domain\Clock\ClockInterface;

final class SystemClock implements ClockInterface
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }
}
