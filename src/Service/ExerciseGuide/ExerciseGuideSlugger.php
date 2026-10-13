<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide;

use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Slug d'une fiche dans une langue, tiré du nom traduit de l'exercice (« Développé couché (barre) » →
 * `developpe-couche-barre`) : aucune traduction de plus à maintenir. Renommer un exercice change donc
 * l'adresse de sa fiche.
 */
final readonly class ExerciseGuideSlugger
{
    public function __construct(
        private ExerciseGuideCatalog $catalog,
        private TranslatorInterface $translator,
        private SluggerInterface $slugger,
    ) {
    }

    public function slugFor(ExerciseGuide $guide, string $locale): string
    {
        $name = $this->translator->trans($guide->exerciseName(), [], 'exercise', $locale);

        return $this->slugger->slug($name, '-', $locale)->lower()->toString();
    }

    public function findBySlug(string $slug, string $locale): ?ExerciseGuide
    {
        foreach ($this->catalog->all() as $guide) {
            if ($this->slugFor($guide, $locale) === $slug) {
                return $guide;
            }
        }

        return null;
    }
}
