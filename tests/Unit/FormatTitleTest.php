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
        $translator = $this->createMock(TranslatorInterface::class);
        $translator
            ->method('trans')
            ->willReturn('FitTracker');

        $this->extension = new FormatTitleExtension($translator);
    }

    /**
     * @return array<int, list<string>>
     */
    public static function specialTitleFormat(): array
    {
        return [
            ['Séance', 'Séance | FitTracker'],
            ['Tableau de bord', 'Tableau de bord | FitTracker'],
            ['ROUTINE', 'Routine | FitTracker'],
            ['Évaluation', 'Évaluation | FitTracker'],
            ['Ç\'EST DE LA BOMBE', 'Ç\'est de la bombe | FitTracker'],
            ['😀test😀', '😀test😀 | FitTracker'],
            [' Espace avant', 'Espace avant | FitTracker'],
            ['Espace après ', 'Espace après | FitTracker'],
            [' Espace avant après ', 'Espace avant après | FitTracker'],
            ['minuscule', 'Minuscule | FitTracker'],
            ['Дмитрий', 'Дмитрий | FitTracker'], // 🇷🇺
            ['23cou', '23cou | FitTracker'],
        ];
    }

    #[DataProvider('specialTitleFormat')]
    public function testFormatTitle(string $word, string $title): void
    {
        $this->assertSame($title, $this->extension->format($word));
    }
}
