<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Export;

use App\Service\Export\CsvNumberFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CsvNumberFormatTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}>
     */
    public static function localeProvider(): array
    {
        return [
            'english' => ['en', ',', '82.5'],
            'french' => ['fr', ';', '82,5'],
            'german' => ['de', ';', '82,5'],
            'polish' => ['pl', ';', '82,5'],
        ];
    }

    /**
     * Excel ouvre directement en colonnes un CSV aux séparateurs de la langue de l'utilisateur.
     */
    #[DataProvider('localeProvider')]
    public function testSeparatorsFollowTheLocale(string $locale, string $columnSeparator, string $formattedWeight): void
    {
        $format = new CsvNumberFormat($locale);

        self::assertSame($columnSeparator, $format->columnSeparator);
        self::assertSame($formattedWeight, $format->decimal(82.5, 2));
    }

    public function testUselessZerosAreDropped(): void
    {
        $format = new CsvNumberFormat('fr');

        self::assertSame('80', $format->decimal(80.0, 2));
        self::assertSame('187,39', $format->decimal(187.3913, 2));
        self::assertSame('0', $format->decimal(0.0, 2));
        self::assertSame('100', $format->decimal(100.0, 2));
        self::assertSame('100', $format->decimal(100.0, 0));
    }
}
