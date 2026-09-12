<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Audit;

final readonly class AuditHistory
{
    /** @param list<AuditEntry> $entries */
    public function __construct(
        public array $entries,
    ) {}
}
