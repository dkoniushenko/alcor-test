<?php

declare(strict_types=1);

namespace Alcor\Shared\Domain\Clock;

interface ClockInterface
{
    public function now(): \DateTimeImmutable;
}
