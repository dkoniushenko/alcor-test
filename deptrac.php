<?php

declare(strict_types=1);

use Deptrac\Deptrac\Contract\Config\Collector\DirectoryConfig;
use Deptrac\Deptrac\Contract\Config\DeptracConfig;
use Deptrac\Deptrac\Contract\Config\Layer;
use Deptrac\Deptrac\Contract\Config\Ruleset;

return static function (DeptracConfig $config): void {
    $config
        ->paths('./src')
        ->layers(
            $payrollDomain = Layer::withName('PayrollDomain')->collectors(
                DirectoryConfig::create('src/Payroll/Domain/.*'),
            ),
            $payrollApplication = Layer::withName('PayrollApplication')->collectors(
                DirectoryConfig::create('src/Payroll/Application/.*'),
            ),
            $payrollInfrastructure = Layer::withName('PayrollInfrastructure')->collectors(
                DirectoryConfig::create('src/Payroll/Infrastructure/.*'),
            ),
            $payrollUi = Layer::withName('PayrollUi')->collectors(
                DirectoryConfig::create('src/Payroll/Ui/.*'),
            ),
            $sharedDomain = Layer::withName('SharedDomain')->collectors(
                DirectoryConfig::create('src/Shared/Domain/.*'),
            ),
            $sharedApplication = Layer::withName('SharedApplication')->collectors(
                DirectoryConfig::create('src/Shared/Application/.*'),
            ),
            $sharedInfrastructure = Layer::withName('SharedInfrastructure')->collectors(
                DirectoryConfig::create('src/Shared/Infrastructure/.*'),
            ),
        )
        ->rulesets(
            Ruleset::forLayer($payrollDomain)->accesses($sharedDomain),
            Ruleset::forLayer($payrollApplication)->accesses($payrollDomain, $sharedDomain, $sharedApplication),
            Ruleset::forLayer($payrollInfrastructure)->accesses($payrollDomain, $sharedDomain, $sharedInfrastructure),
            Ruleset::forLayer($payrollUi)->accesses($payrollApplication, $sharedApplication),
            Ruleset::forLayer($sharedDomain),
            Ruleset::forLayer($sharedApplication)->accesses($sharedDomain),
            Ruleset::forLayer($sharedInfrastructure)->accesses($sharedDomain),
        )
        ->baseline(__DIR__ . '/deptrac.baseline.yaml')
    ;
};
