<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\Translations\LocaleAllowedEnum;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LocaleAllowedEnumTest extends TestCase
{
    /**
     * @return iterable<string, array{LocaleAllowedEnum, string}>
     */
    public static function flagProvider(): iterable
    {
        yield 'english shows the British flag' => [LocaleAllowedEnum::EN, 'gb'];
        yield 'french shows the French flag' => [LocaleAllowedEnum::FR, 'fr'];
        yield 'portuguese shows the Portuguese flag' => [LocaleAllowedEnum::PT, 'pt'];
    }

    #[DataProvider('flagProvider')]
    public function testEachLanguageHasTheFlagOfItsCountry(LocaleAllowedEnum $locale, string $expectedCountry): void
    {
        self::assertSame($expectedCountry, $locale->flagCountryCode());
    }

    public function testEveryLanguageHasAFlagFile(): void
    {
        foreach (LocaleAllowedEnum::cases() as $locale) {
            self::assertFileExists(\dirname(__DIR__, 3) . '/assets/images/flags/' . $locale->flagCountryCode() . '.svg');
        }
    }
}
