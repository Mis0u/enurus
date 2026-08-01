<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Twig\Extension\FormatTitleExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class FormatTitleTest extends TestCase
{
    private FormatTitleExtension $extension;

    protected function setUp(): void
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('Enurus');

        $this->extension = new FormatTitleExtension($translator);
    }

    /**
     * @return array<int, list<string>>
     */
    public static function specialTitleFormat(): array
    {
        return [
            ['Séance', 'Séance | Enurus'],
            ['Tableau de bord', 'Tableau de bord | Enurus'],
            ['ROUTINE', 'Routine | Enurus'],
            ['Évaluation', 'Évaluation | Enurus'],
            ['Ç\'EST DE LA BOMBE', 'Ç\'est de la bombe | Enurus'],
            ['😀test😀', '😀test😀 | Enurus'],
            [' Espace avant', 'Espace avant | Enurus'],
            ['Espace après ', 'Espace après | Enurus'],
            [' Espace avant après ', 'Espace avant après | Enurus'],
            ['minuscule', 'Minuscule | Enurus'],
            ['Дмитрий', 'Дмитрий | Enurus'], // 🇷🇺
            ['23cou', '23cou | Enurus'],
        ];
    }

    #[DataProvider('specialTitleFormat')]
    public function testFormatTitle(string $word, string $title): void
    {
        $this->assertSame($title, $this->extension->format($word));
    }
}
