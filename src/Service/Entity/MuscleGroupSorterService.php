<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\MuscleGroup;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Responsabilité unique : trier une liste de groupes musculaires alphabétiquement
 * selon la locale courante, en tenant compte du nom traduit.
 */
final readonly class MuscleGroupSorterService
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @param list<MuscleGroup> $muscleGroups
     * @return list<MuscleGroup>
     */
    public function sortByName(array $muscleGroups, string $locale): array
    {
        $collator = \Collator::create($locale);

        usort($muscleGroups, function (MuscleGroup $a, MuscleGroup $b) use ($collator): int {
            $nameA = $this->translator->trans($a->name, [], 'muscle');
            $nameB = $this->translator->trans($b->name, [], 'muscle');

            return (int) $collator->compare($nameA, $nameB);
        });

        return $muscleGroups;
    }
}
