<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\AddCorrection;
use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Shared\Domain\Clock\ClockInterface;

final readonly class AddCorrectionHandler
{
    public function __construct(
        private EarningRepositoryInterface $repository,
        private ClockInterface $clock,
    ) {
    }

    public function handle(AddCorrection $command): void
    {
        $earning = $this->repository->find($command->earningId)
            ?? throw new EarningNotFoundException(\sprintf('Earning "%s" not found.', $command->earningId));

        $earning->addCorrection($command->amount, $command->comment, $command->correctedBy, $this->clock);

        $this->repository->save($earning);
    }
}
