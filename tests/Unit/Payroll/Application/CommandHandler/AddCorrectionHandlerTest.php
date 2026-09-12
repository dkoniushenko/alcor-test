<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\AddCorrection;
use Alcor\Payroll\Application\CommandHandler\AddCorrectionHandler;
use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\Event\CorrectionAdded;
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
final class AddCorrectionHandlerTest extends TestCase
{
    public function testHandleAddsCorrectionToExistingEarning(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);
        $earning->pullDomainEvents();
        $command = new AddCorrection(
            $earning->id,
            Money::USD(-4555),
            'Employee declined dental benefit',
            PayrollSpecialistId::generate(),
        );

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects(self::once())->method('find')->with($earning->id)->willReturn($earning);
        $repository->expects(self::once())->method('save')->with($earning);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CorrectionAdded::class))
        ;

        $handler = new AddCorrectionHandler($repository, $clock, $eventDispatcher);

        // When
        $handler->handle($command);

        // Then
        self::assertTrue($earning->isFrozen());
        self::assertTrue(Money::USD(95445)->equals($earning->currentValue()));
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
        new AddCorrectionHandler($repository, $clock, $eventDispatcher)->handle(new AddCorrection(
            $earningId,
            Money::USD(-4555),
            'Employee declined dental benefit',
            PayrollSpecialistId::generate(),
        ));
    }
}
