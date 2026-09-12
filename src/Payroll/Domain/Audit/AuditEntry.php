<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Audit;

use Money\Money;

final readonly class AuditEntry
{
    public function __construct(
        public string $label,
        public Money $value,
    ) {}
}
