<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\ExerciseGuide;

use App\Enum\Translations\LocaleAllowedEnum;
use App\Service\ExerciseGuide\ExerciseGuide;
use App\Service\ExerciseGuide\ExerciseGuideCatalog;
use App\Service\ExerciseGuide\ExerciseGuideSlugger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Translation\MessageCatalogueInterface;
use Symfony\Component\Translation\TranslatorBagInterface;

/**
 * Une fiche est publiée dans les 8 langues : chaque texte déclaré par `ExerciseGuideCatalog` doit
 * exister partout, et rien de plus (une étape traduite mais non déclarée ne s'afficherait jamais).
 */
final class ExerciseGuideTranslationsTest extends KernelTestCase
{
    private const string DOMAIN = 'exercise_guide';

    /**
     * @return iterable<string, array{string}>
     */
    public static function localeProvider(): iterable
    {
        foreach (LocaleAllowedEnum::getAllowedLocale() as $locale) {
            yield $locale => [$locale];
        }
    }

    #[DataProvider('localeProvider')]
    public function testEveryDeclaredTextIsTranslated(string $locale): void
    {
        $catalogue = $this->catalogue($locale);

        foreach ($this->guides() as $guide) {
            foreach ($this->declaredKeys($guide) as $key) {
                self::assertTrue($catalogue->defines($key, self::DOMAIN . MessageCatalogueInterface::INTL_DOMAIN_SUFFIX), \sprintf('Missing "%s" in %s.', $key, $locale));
            }
        }
    }

    #[DataProvider('localeProvider')]
    public function testNoTextIsTranslatedWithoutBeingDeclared(string $locale): void
    {
        $catalogue = $this->catalogue($locale);

        foreach ($this->guides() as $guide) {
            $translatedKeys = array_filter(
                array_keys($catalogue->all(self::DOMAIN . MessageCatalogueInterface::INTL_DOMAIN_SUFFIX)),
                static fn (string $key): bool => str_starts_with($key, $guide->key . '.'),
            );

            self::assertEqualsCanonicalizing($this->declaredKeys($guide), array_values($translatedKeys), \sprintf('Guide "%s" in %s.', $guide->key, $locale));
        }
    }

    #[DataProvider('localeProvider')]
    public function testEachGuideHasItsOwnSlug(string $locale): void
    {
        /** @var ExerciseGuideSlugger $slugger */
        $slugger = static::getContainer()->get(ExerciseGuideSlugger::class);

        $slugs = array_map(static fn (ExerciseGuide $guide): string => $slugger->slugFor($guide, $locale), $this->guides());

        self::assertSame($slugs, array_unique($slugs));
        foreach ($slugs as $slug) {
            self::assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug);
        }
    }

    public function testVariantsAreCatalogueExercises(): void
    {
        $exerciseCatalogue = $this->catalogue(LocaleAllowedEnum::FR->value);

        foreach ($this->guides() as $guide) {
            foreach ($guide->variantKeys as $variantKey) {
                self::assertTrue($exerciseCatalogue->has($variantKey . '.name', 'exercise'), \sprintf('Unknown variant "%s".', $variantKey));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function declaredKeys(ExerciseGuide $guide): array
    {
        return [
            $guide->introKey(),
            ...$guide->stepKeys(),
            ...array_merge(...array_map(array_values(...), $guide->mistakeKeys())),
            ...array_map($guide->variantReasonKey(...), $guide->variantKeys),
        ];
    }

    /**
     * @return list<ExerciseGuide>
     */
    private function guides(): array
    {
        /** @var ExerciseGuideCatalog $catalog */
        $catalog = static::getContainer()->get(ExerciseGuideCatalog::class);

        return $catalog->all();
    }

    private function catalogue(string $locale): MessageCatalogueInterface
    {
        /** @var TranslatorBagInterface $translator */
        $translator = static::getContainer()->get('translator');

        return $translator->getCatalogue($locale);
    }
}
