<?php

declare(strict_types=1);

namespace Alcor\Payroll\Domain\Exception;

use Alcor\Shared\Domain\Exception\AbstractDomainException;

final class CorrectionCommentCannotBeEmptyException extends AbstractDomainException {}
