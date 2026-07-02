<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Responsabilité unique : trier les muscles d'un exercice — primaires d'abord,
 * secondaires ensuite, alphabétiquement au sein de chaque groupe — selon la
 * locale courante, en tenant compte des noms de muscles traduits.
 */
final readonly class MuscleSorterService
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return list<array{label: string, type: MuscleTypeEnum}>
     */
    public function sortByTypeThenName(Exercise $exercise, string $locale): array
    {
        $collator = \Collator::create($locale);

        $resolved = [];
        foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
            $resolved[] = [
                'label' => $this->translator->trans($exerciseMuscle->muscleGroup->name, [], 'muscle', $locale),
                'type' => $exerciseMuscle->type,
            ];
        }

        usort($resolved, function (array $a, array $b) use ($collator): int {
            if ($a['type'] !== $b['type']) {
                return MuscleTypeEnum::PRIMARY === $a['type'] ? -1 : 1;
            }

            return (int) $collator->compare($a['label'], $b['label']);
        });

        return $resolved;
    }
}
