<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;

final class YearInReviewAnnouncementEmailTest extends KernelTestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function locales(): iterable
    {
        yield 'fr' => ['fr', '/fr/mes-resumes/2026'];
        yield 'en' => ['en', '/en/my-recaps/2026'];
        yield 'it' => ['it', '/it/i-miei-riepiloghi/2026'];
        yield 'es' => ['es', '/es/mis-resumenes/2026'];
        yield 'pt' => ['pt', '/pt/meus-resumos/2026'];
        yield 'de' => ['de', '/de/meine-rueckblicke/2026'];
        yield 'nl' => ['nl', '/nl/mijn-overzichten/2026'];
        yield 'pl' => ['pl', '/pl/moje-podsumowania/2026'];
    }

    #[DataProvider('locales')]
    public function testEmailIsTranslatedAndOpensTheScreensInTheOwnerLanguage(string $locale, string $showPath): void
    {
        self::bootKernel();
        /** @var Environment $twig */
        $twig = static::getContainer()->get('twig');

        $html = $twig->render('emails/year_in_review_announcement.html.twig', [
            'year' => 2026,
            'locale' => $locale,
        ]);

        self::assertStringContainsString($showPath . '"', $html);
        self::assertStringContainsString('#email', $html);
        self::assertStringNotContainsString('year_in_review.', $html);
    }
}
