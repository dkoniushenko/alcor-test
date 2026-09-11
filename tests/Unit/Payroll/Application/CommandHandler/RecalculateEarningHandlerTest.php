<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\RecalculateEarning;
use Alcor\Payroll\Application\CommandHandler\RecalculateEarningHandler;
use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\TestCase;

final class RecalculateEarningHandlerTest extends TestCase
{
    public function testHandleUpdatesExistingEarning(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);
        $command = new RecalculateEarning($earning->id, Money::USD(105000));

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects($this->once())->method('find')->with($earning->id)->willReturn($earning);
        $repository->expects($this->once())->method('save')->with($earning);

        $handler = new RecalculateEarningHandler($repository, $clock);

        // When
        $handler->handle($command);

        // Then
        self::assertTrue(Money::USD(105000)->equals($earning->currentValue()));
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
        (new RecalculateEarningHandler($repository, $clock))
            ->handle(new RecalculateEarning($earningId, Money::USD(105000)));
    }
}
