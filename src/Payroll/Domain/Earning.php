<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain;

use Alcor\Payroll\Domain\Event\CorrectionAdded;
use Alcor\Payroll\Domain\Event\EarningCalculated;
use Alcor\Payroll\Domain\ValueObject\CorrectionId;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Alcor\Shared\Domain\Clock\ClockInterface;
use Alcor\Shared\Domain\Event\RecordsDomainEventsTrait;
use Money\Money;

final class Earning
{
    use RecordsDomainEventsTrait;

    /** @var list<Correction> */
    private array $corrections = [];

    private function __construct(
        public readonly EarningId $id,
        public readonly EmployeeId $employeeId,
        private Money $calculatedValue,
        private \DateTimeImmutable $calculatedAt,
    ) {
    }

    public static function calculate(EmployeeId $employeeId, Money $calculatedValue, ClockInterface $clock): self
    {
        $now = $clock->now();
        $earning = new self(EarningId::generate(), $employeeId, $calculatedValue, $now);
        $earning->record(new EarningCalculated($earning->id, $calculatedValue, $now));

        return $earning;
    }

    public function recalculate(Money $candidate, ClockInterface $clock): void
    {
        if ($this->isFrozen()) {
            return;
        }

        $now = $clock->now();
        $this->calculatedValue = $candidate;
        $this->calculatedAt = $now;
        $this->record(new EarningCalculated($this->id, $candidate, $now));
    }

    public function addCorrection(
        Money $amount,
        string $comment,
        PayrollSpecialistId $correctedBy,
        ClockInterface $clock,
    ): void {
        $correction = new Correction(
            CorrectionId::generate(),
            $amount,
            $comment,
            $correctedBy,
            $clock->now(),
        );

        $this->corrections[] = $correction;

        $this->record(new CorrectionAdded(
            $this->id,
            $correction->id,
            $correction->amount,
            $correction->comment,
            $correction->correctedBy,
            $correction->recordedAt,
        ));
    }

    public function isFrozen(): bool
    {
        return [] !== $this->corrections;
    }

    public function calculatedAt(): \DateTimeImmutable
    {
        return $this->calculatedAt;
    }

    public function currentValue(): Money
    {
        $total = $this->calculatedValue;

        foreach ($this->corrections as $correction) {
            $total = $total->add($correction->amount);
        }

        return $total;
    }
}
