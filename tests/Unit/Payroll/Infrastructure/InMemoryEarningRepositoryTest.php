<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Infrastructure;

use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEarningRepository;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class InMemoryEarningRepositoryTest extends TestCase
{
    public function testFindReturnsNullWhenNothingIsStored(): void
    {
        // Given
        $repository = new InMemoryEarningRepository();

        // When
        $found = $repository->find(EarningId::generate());

        // Then
        self::assertNull($found);
    }

    public function testSaveThenFindReturnsTheSameEarning(): void
    {
        // Given
        $repository = new InMemoryEarningRepository();
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);

        // When
        $repository->save($earning);
        $found = $repository->find($earning->id);

        // Then
        self::assertSame($earning, $found);
    }
}
