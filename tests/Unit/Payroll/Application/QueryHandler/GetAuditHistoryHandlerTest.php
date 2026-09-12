<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Application\QueryHandler;

use Alcor\Payroll\Application\Exception\EarningNotFoundException;
use Alcor\Payroll\Application\Query\GetAuditHistory;
use Alcor\Payroll\Application\QueryHandler\GetAuditHistoryHandler;
use Alcor\Payroll\Domain\Earning;
use Alcor\Payroll\Domain\EarningRepositoryInterface;
use Alcor\Payroll\Domain\ValueObject\EarningId;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Tests\Fixtures\FixedClock;
use Money\Money;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class GetAuditHistoryHandlerTest extends TestCase
{
    public function testHandleReturnsEarningsAuditHistory(): void
    {
        // Given
        $clock = new FixedClock(new \DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $earning = Earning::calculate(EmployeeId::generate(), Money::USD(100000), $clock);

        $repository = $this->createMock(EarningRepositoryInterface::class);
        $repository->expects(self::once())->method('find')->with($earning->id)->willReturn($earning);

        $handler = new GetAuditHistoryHandler($repository);

        // When
        $history = $handler->handle(new GetAuditHistory($earning->id));

        // Then
        self::assertCount(2, $history->entries);
        self::assertSame('Calculated value', $history->entries[0]->label);
        self::assertSame('Current value', $history->entries[1]->label);
    }

    public function testHandleThrowsWhenEarningDoesNotExist(): void
    {
        // Given
        $earningId = EarningId::generate();

        $repository = self::createStub(EarningRepositoryInterface::class);
        $repository->method('find')->willReturn(null);

        // Expects
        $this->expectException(EarningNotFoundException::class);

        // When
        new GetAuditHistoryHandler($repository)->handle(new GetAuditHistory($earningId));
    }
}
