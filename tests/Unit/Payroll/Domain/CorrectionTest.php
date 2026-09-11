<?php

declare(strict_types=1);

namespace Alcor\Tests\Unit\Payroll\Domain;

use Alcor\Payroll\Domain\Correction;
use Alcor\Payroll\Domain\Exception\CorrectionAmountCannotBeZeroException;
use Alcor\Payroll\Domain\Exception\CorrectionCommentCannotBeEmptyException;
use Alcor\Payroll\Domain\ValueObject\CorrectionId;
use Alcor\Payroll\Domain\ValueObject\PayrollSpecialistId;
use Money\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CorrectionTest extends TestCase
{
    #[DataProvider('positiveDataProvider')]
    public function testPositive(Money $amount): void
    {
        $id = CorrectionId::generate();
        $comment = 'Correcting mistake in adjustment #4';
        $correctedBy = PayrollSpecialistId::generate();
        $recordedAt = new \DateTimeImmutable('2026-01-01T00:00:00+00:00');

        $correction = new Correction($id, $amount, $comment, $correctedBy, $recordedAt);

        self::assertTrue($id->equals($correction->id));
        self::assertTrue($amount->equals($correction->amount));
        self::assertSame($comment, $correction->comment);
        self::assertTrue($correctedBy->equals($correction->correctedBy));
        self::assertSame($recordedAt, $correction->recordedAt);
    }

    /** @return iterable<string, array{string}> */
    public static function positiveDataProvider(): iterable
    {
        yield 'it supports positive amount' => [
            'amount' => Money::USD(100),
        ];
        yield 'it supports negative amount' => [
            'amount' => Money::USD(-233),
        ];
    }

    public function testConstructingWithAZeroAmountIsRejected(): void
    {
        $this->expectException(CorrectionAmountCannotBeZeroException::class);

        new Correction(
            CorrectionId::generate(),
            Money::USD(0),
            'Correcting mistake in adjustment #4',
            PayrollSpecialistId::generate(),
            new \DateTimeImmutable(),
        );
    }

    #[DataProvider('invalidCommentProvider')]
    public function testConstructingWithAnInvalidCommentIsRejected(string $comment): void
    {
        $this->expectException(CorrectionCommentCannotBeEmptyException::class);

        new Correction(
            CorrectionId::generate(),
            Money::USD(-4555),
            $comment,
            PayrollSpecialistId::generate(),
            new \DateTimeImmutable(),
        );
    }

    /** @return iterable<string, array{string}> */
    public static function invalidCommentProvider(): iterable
    {
        yield 'empty string' => [''];
        yield 'single space' => [' '];
        yield 'tab' => ["\t"];
        yield 'newline' => ["\n"];
        yield 'mixed whitespace' => ["  \t\n  "];
    }
}
