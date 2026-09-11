<?php

declare(strict_types=1);

namespace Alcor\Payroll\Infrastructure\Persistence;

use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\ValueObject\EarningId;

final class InMemoryEarningRepository implements EarningRepositoryInterface
{
    /** @var array<string, Earning> */
    private array $earnings = [];

    public function find(EarningId $id): ?Earning
    {
        return $this->earnings[(string) $id] ?? null;
    }

    public function save(Earning $earning): void
    {
        $this->earnings[(string) $earning->id] = $earning;
    }
}
