<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Event;

use Alcor\Payroll\Domain\ValueObject\CorrectionId;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Alcor\Shared\Domain\Event\AbstractDomainEvent;
use Money\Money;

final readonly class CorrectionAdded extends AbstractDomainEvent
{
    public function __construct(
        public EarningId $earningId,
        public CorrectionId $correctionId,
        public Money $amount,
        public string $comment,
        public PayrollSpecialistId $correctedBy,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }
}
