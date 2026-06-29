<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Responsabilité unique : trier une liste d'exercices alphabétiquement
 * selon la locale courante, en tenant compte des exercices traduits (publics).
 */
final class ExerciseSorterService
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<Exercise> $exercises
     * @return list<Exercise>
     */
    public function sortByName(array $exercises, string $locale): array
    {
        $collator = \Collator::create($locale);

        usort($exercises, function (Exercise $a, Exercise $b) use ($collator): int {
            $nameA = $a->isPublic ? $this->translator->trans($a->name, [], 'exercise') : $a->name;
            $nameB = $b->isPublic ? $this->translator->trans($b->name, [], 'exercise') : $b->name;

            return (int) $collator->compare($nameA, $nameB);
        });

        return $exercises;
    }
}
