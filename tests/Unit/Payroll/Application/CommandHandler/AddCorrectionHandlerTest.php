<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\AddCorrection;
use Alcor\Payroll\Application\CommandHandler\AddCorrectionHandler;
use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class AddCorrectionHandlerTest extends TestCase
{
    public function testHandleAddsCorrectionToExistingEarning(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);
        $command = new AddCorrection(
            $earning->id,
            Money::USD(-4555),
            'Employee declined dental benefit',
            PayrollSpecialistId::generate(),
        );

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects($this->once())->method('find')->with($earning->id)->willReturn($earning);
        $repository->expects($this->once())->method('save')->with($earning);

        $handler = new AddCorrectionHandler($repository, $clock);

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
        $repository->expects($this->never())->method('save');

        // Expects
        $this->expectException(EarningNotFoundException::class);

        // When
        (new AddCorrectionHandler($repository, $clock))->handle(new AddCorrection(
            $earningId,
            Money::USD(-4555),
            'Employee declined dental benefit',
            PayrollSpecialistId::generate(),
        ));
    }
}
