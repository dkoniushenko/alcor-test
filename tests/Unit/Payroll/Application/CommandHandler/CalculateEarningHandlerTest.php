<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Application\CommandHandler;

use Alcor\Payroll\Application\Command\CalculateEarning;
use Alcor\Payroll\Application\CommandHandler\CalculateEarningHandler;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class CalculateEarningHandlerTest extends TestCase
{
    public function testHandleSavesNewEarningAndReturnsItsId(): void
    {
        // Given
        $employeeId = EmployeeId::generate();
        $command = new CalculateEarning($employeeId, Money::USD(100000));
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $savedEarning = null;
        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('save')
            ->willReturnCallback(static function (Earning $earning) use (&$savedEarning): void {
                $savedEarning = $earning;
            })
        ;

        $handler = new CalculateEarningHandler($repository, $clock);

        // When
        $earningId = $handler->handle($command);

        // Then
        self::assertNotNull($savedEarning);
        self::assertTrue($earningId->equals($savedEarning->id));
        self::assertTrue($employeeId->equals($savedEarning->employeeId));
        self::assertTrue(Money::USD(100000)->equals($savedEarning->currentValue()));
    }
}
