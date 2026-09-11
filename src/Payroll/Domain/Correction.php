<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain;

use Alcor\Payroll\Domain\Exception\CorrectionAmountCannotBeZeroException;
use Alcor\Payroll\Domain\Exception\CorrectionCommentCannotBeEmptyException;
use Alcor\Payroll\Domain\ValueObject\CorrectionId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Money\Money;

final readonly class Correction
{
    public function __construct(
        public CorrectionId $id,
        public Money $amount,
        public string $comment,
        public PayrollSpecialistId $correctedBy,
        public \DateTimeImmutable $recordedAt,
    ) {
        if ('' === trim($comment)) {
            throw new CorrectionCommentCannotBeEmptyException('Correction comment cannot be empty.');
        }

        if ($amount->isZero()) {
            throw new CorrectionAmountCannotBeZeroException('Correction amount cannot be zero.');
        }
    }
}
