<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Money\Money;

final readonly class AddCorrection
{
    public function __construct(
        public EarningId $earningId,
        public Money $amount,
        public string $comment,
        public PayrollSpecialistId $correctedBy,
    ) {
    }
}
