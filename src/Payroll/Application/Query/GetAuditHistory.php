<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\Query;

use Alcor\Payroll\Domain\ValueObject\EarningId;

final readonly class GetAuditHistory
{
    public function __construct(
        public EarningId $earningId,
    ) {
    }
}
