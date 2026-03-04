<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\Extension\InitialExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class InitialExtensionTest extends TestCase
{
    private InitialExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new InitialExtension();
    }

    /**
     * @return array<int, list<string>>
     */
    public static function specialName(): array
    {
        return [
            ['Étienne', 'ÉE'], // 🇫🇷
            ['Noé', 'NÉ'], // 🇫🇷
            ['Jean-Pierre', 'JE'], // 🇫🇷
            ['Marie Anne', 'ME'], // 🇫🇷
            ['pierre', 'PE'], // 🇫🇷 en minuscule
            ['JACQUES', 'JS'], // 🇫🇷 en majuscule
            ['Giosuè', 'GÈ'], // 🇮🇹
            ['Ângela', 'ÂA'], // 🇵🇹
            ['José', 'JÉ'], // 🇪🇸
            ['Ángela', 'ÁA'], // 🇪🇸
            ['Дмитрий', 'ДЙ'], // 🇷🇺
            [' test avec espace ', 'TE'],
            ['23cou', '2U'],
            ['cou23', 'C3'],
            ['1234', '14'],
            ['1cou´&', '1&'],
            ['😀test😀', '😀😀'],
            ['😀tes😀t', '😀T'],
            ['test😀', 'T😀'],
        ];
    }

    public function testInitial(): void
    {
        $name = 'Mickaël';
        $this->assertSame('ML', $this->extension->initials($name));
    }

    #[DataProvider('specialName')]
    public function testInitialWithSpecialChar(string $name, string $initials): void
    {
        $this->assertSame($initials, $this->extension->initials($name));
    }
}
