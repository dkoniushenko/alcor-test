<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain;

use Alcor\Payroll\Domain\ValueObject\EarningId;

interface EarningRepositoryInterface
{
    public function find(EarningId $id): ?Earning;

    public function save(Earning $earning): void;
}
