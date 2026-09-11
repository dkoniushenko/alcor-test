<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\QueryHandler;

use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Application\Query\GetAuditHistory;
use Alcor\Payroll\Domain\Audit\AuditHistory;
use Alcor\Payroll\Domain\EarningRepositoryInterface;

final readonly class GetAuditHistoryHandler
{
    public function __construct(
        private EarningRepositoryInterface $repository,
    ) {
    }

    public function handle(GetAuditHistory $query): AuditHistory
    {
        $earning = $this->repository->find($query->earningId)
            ?? throw new EarningNotFoundException(\sprintf('Earning "%s" not found.', $query->earningId));

        return $earning->auditHistory();
    }
}
