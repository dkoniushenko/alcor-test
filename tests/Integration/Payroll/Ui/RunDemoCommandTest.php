<?php

declare(strict_types=1);

namespace Alcor\Tests\Integration\Payroll\Ui;

use Alcor\Payroll\Application\CommandHandler\AddCorrectionHandler;
use Alcor\Payroll\Application\CommandHandler\CalculateEarningHandler;
use Alcor\Payroll\Application\CommandHandler\RecalculateEarningHandler;
use Alcor\Payroll\Application\QueryHandler\GetAuditHistoryHandler;
use Alcor\Payroll\Infrastructure\Persistence\InMemoryEarningRepository;
use Alcor\Payroll\Ui\Cli\RunDemoCommand;
use Alcor\Shared\Infrastructure\Clock\SystemClock;
use Alcor\Shared\Infrastructure\Event\ConsoleEventDispatcher;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class RunDemoCommandTest extends TestCase
{
    public function testRunPrintsTheFinalAuditHistoryMatchingTheAssignment(): void
    {
        // Given
        $repository = new InMemoryEarningRepository();
        $clock = new SystemClock();
        $eventDispatcher = new ConsoleEventDispatcher();
        $command = new RunDemoCommand(
            new CalculateEarningHandler($repository, $clock, $eventDispatcher),
            new RecalculateEarningHandler($repository, $clock, $eventDispatcher),
            new AddCorrectionHandler($repository, $clock, $eventDispatcher),
            new GetAuditHistoryHandler($repository),
        );

        // When
        ob_start();
        $command->run();
        $output = ob_get_clean();
        \assert(\is_string($output));

        // Then
        self::assertStringContainsString('[event] EarningCalculated occurred at', $output);
        self::assertStringContainsString('[event] CorrectionAdded occurred at', $output);
        self::assertStringContainsString('Calculated value (frozen)', $output);
        self::assertStringContainsString('$1,050.00', $output);
        self::assertStringContainsString('-$45.55', $output);
        self::assertStringContainsString('$100.10', $output);
        self::assertStringContainsString('-$0.10', $output);
        self::assertStringContainsString('-$0.20', $output);
        self::assertStringContainsString('Current value', $output);
        self::assertStringEndsWith("\$1,104.45\n", $output);
    }
}
