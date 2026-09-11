<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

use Alcor\Payroll\Domain\ValueObject\EarningId;
use Money\Money;

final readonly class RecalculateEarning
{
    public function __construct(
        public EarningId $earningId,
        public Money $candidate,
    ) {
    }
}
