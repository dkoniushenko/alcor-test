<?php

declare(strict_types=1);

namespace Alcor\Shared\Domain\ValueObject;

use Symfony\Component\Uid\UuidV7;

abstract readonly class AbstractUuidId implements \Stringable
{
    private function __construct(
        private UuidV7 $value,
    ) {
    }

    public static function generate(): static
    {
        return new static(new UuidV7());
    }

    public static function fromString(string $value): static
    {
        return new static(UuidV7::fromString($value));
    }

    // Parameter is `self`, not `static` (PHP disallows `static` in parameter
    // position) — the extra `static::class` check below is what actually stops an
    // EarningId from comparing equal to a CorrectionId sharing the same UUID.
    public function equals(self $other): bool
    {
        return static::class === $other::class && $this->value->equals($other->value);
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
