<?php

declare(strict_types=1);

namespace Alcor\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\CalculateEarning;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Shared\Application\Event\EventDispatcherInterface;
use Alcor\Shared\Domain\Clock\ClockInterface;

final readonly class CalculateEarningHandler
{
    public function __construct(
        private EarningRepositoryInterface $repository,
        private ClockInterface $clock,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function handle(CalculateEarning $command): EarningId
    {
        $earning = Earning::calculate($command->employeeId, $command->calculatedValue, $this->clock);

        $this->repository->save($earning);

        foreach ($earning->pullDomainEvents() as $event) {
            $this->eventDispatcher->dispatch($event);
        }

        return $earning->id;
    }
}
