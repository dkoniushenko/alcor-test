<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Shared\Domain\ValueObject;

use Alcor\Tests\Fixtures\DummyUuidIdA;
use Alcor\Tests\Fixtures\DummyUuidIdB;
use PHPUnit\Framework\TestCase;

final class AbstractUuidIdTest extends TestCase
{
    public function testGenerateReturnsInstanceOfTheConcreteClass(): void
    {
        self::assertInstanceOf(DummyUuidIdA::class, DummyUuidIdA::generate());
    }

    public function testFromStringRoundTrips(): void
    {
        $id = DummyUuidIdA::generate();

        self::assertTrue($id->equals(DummyUuidIdA::fromString((string) $id)));
    }

    public function testEqualsIsTrueForTheSameValue(): void
    {
        $id = DummyUuidIdA::generate();
        $same = DummyUuidIdA::fromString((string) $id);

        self::assertTrue($id->equals($same));
    }

    public function testEqualsIsFalseForADifferentValue(): void
    {
        self::assertFalse(DummyUuidIdA::generate()->equals(DummyUuidIdA::generate()));
    }

    public function testToStringReturnsAValidUuidV7(): void
    {
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            (string) DummyUuidIdA::generate(),
        );
    }

    public function testEqualsIsFalseForADifferentConcreteTypeEvenWithTheSameUnderlyingUuid(): void
    {
        $a = DummyUuidIdA::generate();
        $b = DummyUuidIdB::fromString((string) $a);

        self::assertFalse($a->equals($b));
    }
}
