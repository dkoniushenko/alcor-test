<?php

declare(strict_types=1);

namespace Alcor\Payroll\Ui\Cli;

use Alcor\Payroll\Application\Command\AddCorrection;
use Alcor\Payroll\Application\Command\CalculateEarning;
use Alcor\Payroll\Application\Command\RecalculateEarning;
use Alcor\Payroll\Application\CommandHandler\AddCorrectionHandler;
use Alcor\Payroll\Application\CommandHandler\CalculateEarningHandler;
use Alcor\Payroll\Application\CommandHandler\RecalculateEarningHandler;
use Alcor\Payroll\Application\Query\GetAuditHistory;
use Alcor\Payroll\Application\QueryHandler\GetAuditHistoryHandler;
use Alcor\Payroll\Domain\ValueObject\EmployeeId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Money\Currencies\ISOCurrencies;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Money\MoneyFormatter;
use NumberFormatter;

final class RunDemoCommand
{
    private readonly MoneyFormatter $moneyFormatter;

    public function __construct(
        private readonly CalculateEarningHandler $calculateEarningHandler,
        private readonly RecalculateEarningHandler $recalculateEarningHandler,
        private readonly AddCorrectionHandler $addCorrectionHandler,
        private readonly GetAuditHistoryHandler $getAuditHistoryHandler,
    ) {
        $this->moneyFormatter = new IntlMoneyFormatter(
            new NumberFormatter('en_US', NumberFormatter::CURRENCY),
            new ISOCurrencies(),
        );
    }

    public function run(): void
    {
        $employeeId = EmployeeId::generate();
        $specialist = PayrollSpecialistId::generate();

        // TODO: add numbers for calculations

        echo "1. System calculates the line.\n";
        $earningId = $this->calculateEarningHandler->handle(
            new CalculateEarning($employeeId, Money::USD(100000)),
        );

        echo "2. Source data changes, system recalculates (no correction yet, allowed).\n";
        $this->recalculateEarningHandler->handle(
            new RecalculateEarning($earningId, Money::USD(105000)),
        );

        echo "3. Specialist adds a manual correction.\n";
        $this->addCorrectionHandler->handle(
            new AddCorrection(
                $earningId,
                Money::USD(-4555),
                'Employee declined dental benefit; reversing deduction',
                $specialist,
            ),
        );

        echo "4. Source data changes again, system attempts to recalculate — ignored (already frozen).\n";
        $this->recalculateEarningHandler->handle(
            new RecalculateEarning($earningId, Money::USD(999999)),
        );

        echo "5. Specialist adds a second correction.\n";
        $this->addCorrectionHandler->handle(
            new AddCorrection(
                $earningId,
                Money::USD(10010),
                'Late correction: missed approved overtime bonus',
                $specialist,
            ),
        );

        echo "6. Specialist adds a third correction.\n";
        $this->addCorrectionHandler->handle(
            new AddCorrection(
                $earningId,
                Money::USD(-10),
                'Minor rounding adjustment',
                $specialist,
            ),
        );

        echo "7. Specialist adds a fourth correction.\n";
        $this->addCorrectionHandler->handle(
            new AddCorrection(
                $earningId,
                Money::USD(-20),
                'Second minor rounding adjustment',
                $specialist,
            ),
        );

        echo "8. Specialist adds a compensating correction, realizing step 7 was a mistake.\n";
        $this->addCorrectionHandler->handle(
            new AddCorrection(
                $earningId,
                Money::USD(20),
                'Correcting mistake in adjustment #4',
                $specialist,
            ),
        );

        echo "\nFinal audit history:\n";
        $history = $this->getAuditHistoryHandler->handle(new GetAuditHistory($earningId));

        foreach ($history->entries as $entry) {
            $label = mb_str_pad($entry->label, 68);
            $amount = mb_str_pad($this->moneyFormatter->format($entry->value), 12, ' ', STR_PAD_LEFT);

            echo "  {$label} {$amount}\n";
        }
    }
}
