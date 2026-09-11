<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Event;

use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Shared\Domain\Event\AbstractDomainEvent;
use Money\Money;

final readonly class EarningCalculated extends AbstractDomainEvent
{
    public function __construct(
        public EarningId $earningId,
        public Money $calculatedValue,
        \DateTimeImmutable $occurredAt,
    ) {
        parent::__construct($occurredAt);
    }
}
