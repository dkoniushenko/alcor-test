<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\RecalculateEarning;
use Alcor\Payroll\Application\CommandHandler\RecalculateEarningHandler;
use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\Event\EarningCalculated;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Alcor\Shared\Application\Event\EventDispatcherInterface;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RecalculateEarningHandlerTest extends TestCase
{
    public function testHandleUpdatesExistingEarning(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);
        $earning->pullDomainEvents();
        $command = new RecalculateEarning($earning->id, Money::USD(105000));

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects(self::once())->method('find')->with($earning->id)->willReturn($earning);
        $repository->expects(self::once())->method('save')->with($earning);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(EarningCalculated::class))
        ;

        $handler = new RecalculateEarningHandler($repository, $clock, $eventDispatcher);

        // When
        $handler->handle($command);

        // Then
        self::assertTrue(Money::USD(105000)->equals($earning->currentValue()));
    }

    public function testHandleWhenEarningIsFrozenDoesNotDispatchAnyEvent(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);
        $earning->addCorrection(Money::USD(-500), 'Employee declined dental benefit', PayrollSpecialistId::generate(), $clock);
        $earning->pullDomainEvents();
        $command = new RecalculateEarning($earning->id, Money::USD(999999));

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects(self::once())->method('find')->with($earning->id)->willReturn($earning);
        $repository->expects(self::once())->method('save')->with($earning);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        $handler = new RecalculateEarningHandler($repository, $clock, $eventDispatcher);

        // When
        $handler->handle($command);

        // Then
        self::assertTrue(Money::USD(99500)->equals($earning->currentValue()));
    }

    public function testHandleThrowsWhenEarningDoesNotExist(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earningId = EarningId::generate();

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->method('find')->willReturn(null);
        $repository->expects(self::never())->method('save');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects(self::never())->method('dispatch');

        // Expects
        $this->expectException(EarningNotFoundException::class);

        // When
        new RecalculateEarningHandler($repository, $clock, $eventDispatcher)
            ->handle(new RecalculateEarning($earningId, Money::USD(105000)))
        ;
    }
}
