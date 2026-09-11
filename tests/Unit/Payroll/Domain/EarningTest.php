<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Domain;

use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\Event\CorrectionAdded;
use Alcor\Payroll\Domain\Event\EarningCalculated;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class EarningTest extends TestCase
{
    private FixedClock $clock;

    protected function setUp(): void
    {
        $this->clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
    }

    #[DataProvider('calculatedValueProvider')]
    public function testCalculate(Money $calculatedValue): void
    {
        // When
        $earning = Earning::calculate(EmployeeId::generate(), $calculatedValue, $this->clock);

        // Then
        self::assertTrue($calculatedValue->equals($earning->currentValue()));
        self::assertEquals($this->clock->now(), $earning->calculatedAt());

        $events = $earning->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(EarningCalculated::class, $events[0]);
        self::assertTrue($earning->id->equals($events[0]->earningId));
        self::assertTrue($calculatedValue->equals($events[0]->calculatedValue));
    }

    /** @return iterable<string, array{Money}> */
    public static function calculatedValueProvider(): iterable
    {
        yield 'positive value' => [Money::USD(10000)];
        yield 'zero is allowed (unlike a Correction amount)' => [Money::USD(0)];
        yield 'negative value' => [Money::USD(-500)];
    }

    public function testRecalculateWhenNotFrozen(): void
    {
        // Given
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(10000), $this->clock);
        $laterClock = new FixedClock(new \DateTimeImmutable('2026-06-01T00:00:00+00:00'));

        // When
        $earning->recalculate(Money::USD(20000), $laterClock);

        // Then
        self::assertTrue(Money::USD(20000)->equals($earning->currentValue()));
        self::assertEquals($laterClock->now(), $earning->calculatedAt());
    }

    public function testRecalculateWhenFrozen(): void
    {
        // Given
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(10000), $this->clock);
        $earning->addCorrection(Money::USD(-3000), 'Employee declined dental benefit', PayrollSpecialistId::generate(), $this->clock);
        $calculatedAtBefore = $earning->calculatedAt();
        $earning->pullDomainEvents();

        // When
        $laterClock = new FixedClock(new \DateTimeImmutable('2026-06-01T00:00:00+00:00'));
        $earning->recalculate(Money::USD(20000), $laterClock);

        // Then
        self::assertTrue(Money::USD(7000)->equals($earning->currentValue()));
        self::assertEquals($calculatedAtBefore, $earning->calculatedAt());
        self::assertSame([], $earning->pullDomainEvents());
    }

    #[DataProvider('correctionAmountProvider')]
    public function testAddCorrection(Money $amount): void
    {
        // Given
        $baseValue = Money::USD(100000);
        $earning = Earning::calculate(EmployeeId::generate(), $baseValue, $this->clock);
        $earning->pullDomainEvents();
        $correctedBy = PayrollSpecialistId::generate();

        self::assertFalse($earning->isFrozen());

        // When
        $earning->addCorrection($amount, 'Employee declined dental benefit', $correctedBy, $this->clock);

        // Then
        self::assertTrue($earning->isFrozen());
        self::assertTrue($baseValue->add($amount)->equals($earning->currentValue()));

        $events = $earning->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(CorrectionAdded::class, $events[0]);
        self::assertTrue($earning->id->equals($events[0]->earningId));
        self::assertTrue($amount->equals($events[0]->amount));
        self::assertSame('Employee declined dental benefit', $events[0]->comment);
        self::assertTrue($correctedBy->equals($events[0]->correctedBy));
    }

    /** @return iterable<string, array{Money}> */
    public static function correctionAmountProvider(): iterable
    {
        yield 'negative correction' => [Money::USD(-4555)];
        yield 'positive correction' => [Money::USD(10010)];
    }

    public function testAuditHistoryBeforeAnyCorrection(): void
    {
        // Given
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $this->clock);

        // When
        $history = $earning->auditHistory();

        // Then
        self::assertCount(2, $history->entries);
        self::assertSame('Calculated value', $history->entries[0]->label);
        self::assertTrue(Money::USD(100000)->equals($history->entries[0]->value));
        self::assertSame('Current value', $history->entries[1]->label);
        self::assertTrue(Money::USD(100000)->equals($history->entries[1]->value));
    }

    public function testTheFullWorkedExampleFromTheAssignment(): void
    {
        $employeeId = EmployeeId::generate();
        $specialist = PayrollSpecialistId::generate();

        // Step 1: System calculates the line.
        $earning = Earning::calculate($employeeId, Money::USD(100000), $this->clock);
        self::assertTrue(Money::USD(100000)->equals($earning->currentValue()));

        // Step 2: Source data changes, system recalculates (no correction yet, allowed).
        $earning->recalculate(Money::USD(105000), $this->clock);
        self::assertTrue(Money::USD(105000)->equals($earning->currentValue()));

        // Step 3: Specialist adds a manual correction.
        $earning->addCorrection(
            Money::USD(-4555),
            'Employee declined dental benefit; reversing deduction',
            $specialist,
            $this->clock,
        );
        self::assertTrue(Money::USD(100445)->equals($earning->currentValue()));

        // Step 4: Source data changes again, system attempts to recalculate — must be ignored.
        $earning->recalculate(Money::USD(999999), $this->clock);
        self::assertTrue(Money::USD(100445)->equals($earning->currentValue()));

        // Step 5: Specialist adds a second correction.
        $earning->addCorrection(
            Money::USD(10010),
            'Late correction: missed approved overtime bonus',
            $specialist,
            $this->clock,
        );
        self::assertTrue(Money::USD(110455)->equals($earning->currentValue()));

        // Step 6: Specialist adds a third correction.
        $earning->addCorrection(Money::USD(-10), 'Minor rounding adjustment', $specialist, $this->clock);
        self::assertTrue(Money::USD(110445)->equals($earning->currentValue()));

        // Step 7: Specialist adds a fourth correction.
        $earning->addCorrection(Money::USD(-20), 'Second minor rounding adjustment', $specialist, $this->clock);
        self::assertTrue(Money::USD(110425)->equals($earning->currentValue()));

        // Step 8: Specialist adds a compensating correction, realizing step 7 was a mistake.
        $earning->addCorrection(Money::USD(20), 'Correcting mistake in adjustment #4', $specialist, $this->clock);
        self::assertTrue(Money::USD(110445)->equals($earning->currentValue()));

        // Final audit history matches the assignment's "Expected final audit history" table.
        $history = $earning->auditHistory();
        self::assertCount(7, $history->entries);

        self::assertSame('Calculated value (frozen)', $history->entries[0]->label);
        self::assertTrue(Money::USD(105000)->equals($history->entries[0]->value));

        self::assertSame(
            'Correction 1 — Employee declined dental benefit; reversing deduction',
            $history->entries[1]->label,
        );
        self::assertTrue(Money::USD(-4555)->equals($history->entries[1]->value));

        self::assertSame(
            'Correction 2 — Late correction: missed approved overtime bonus',
            $history->entries[2]->label,
        );
        self::assertTrue(Money::USD(10010)->equals($history->entries[2]->value));

        self::assertSame('Correction 3 — Minor rounding adjustment', $history->entries[3]->label);
        self::assertTrue(Money::USD(-10)->equals($history->entries[3]->value));

        self::assertSame('Correction 4 — Second minor rounding adjustment', $history->entries[4]->label);
        self::assertTrue(Money::USD(-20)->equals($history->entries[4]->value));

        self::assertSame('Correction 5 — Correcting mistake in adjustment #4', $history->entries[5]->label);
        self::assertTrue(Money::USD(20)->equals($history->entries[5]->value));

        self::assertSame('Current value', $history->entries[6]->label);
        self::assertTrue(Money::USD(110445)->equals($history->entries[6]->value));
    }
}
