<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Command;

use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Money\Money;

final readonly class CalculateEarning
{
    public function __construct(
        public EmployeeId $employeeId,
        public Money $calculatedValue,
    ) {
    }
}
