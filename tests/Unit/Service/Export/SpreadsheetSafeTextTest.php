<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use App\Service\Export\SpreadsheetSafeText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SpreadsheetSafeTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function formulaProvider(): iterable
    {
        yield 'equals' => ['=HYPERLINK("https://evil.test")'];
        yield 'plus' => ['+1+1'];
        yield 'minus' => ['-1+1'];
        yield 'at' => ['@SUM(A1)'];
        yield 'tab' => ["\t=1"];
        yield 'carriage return' => ["\r=1"];
    }

    #[DataProvider('formulaProvider')]
    public function testATextASpreadsheetWouldRunAsAFormulaIsKeptAsText(string $text): void
    {
        self::assertSame("'" . $text, SpreadsheetSafeText::escape($text));
    }

    public function testAnOrdinaryNameIsLeftUntouched(): void
    {
        self::assertSame('Push day', SpreadsheetSafeText::escape('Push day'));
        self::assertSame('', SpreadsheetSafeText::escape(''));
    }
}
