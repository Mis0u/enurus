<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide;

use App\Entity\Exercise;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\ExerciseRepository;
use App\Service\ExerciseGuide\View\ExerciseGuideLink;
use App\Service\ExerciseGuide\View\ExerciseGuideMuscleGroup;

/**
 * Index des fiches publiques : chaque exercice rangé sous ses muscles principaux (le développé
 * couché apparaît sous les pectoraux et sous les deltoïdes antérieurs), groupes dans l'ordre de l'appli.
 */
final readonly class ExerciseGuideIndexBuilder
{
    public function __construct(
        private ExerciseRepository $exerciseRepository,
        private ExerciseGuideCatalog $catalog,
        private ExerciseGuideSlugger $slugger,
    ) {
    }

    /**
     * @return list<ExerciseGuideMuscleGroup>
     */
    public function build(string $locale): array
    {
        $groups = [];
        foreach ($this->guidesWithTheirExercise() as [$guide, $exercise]) {
            $link = new ExerciseGuideLink($exercise->name, $this->slugger->slugFor($guide, $locale));
            foreach ($this->primaryMuscleGroups($exercise) as $muscleGroup) {
                $groups[$muscleGroup->name]['muscleGroup'] = $muscleGroup;
                $groups[$muscleGroup->name]['exercises'][] = $link;
            }
        }

        usort($groups, static fn (array $a, array $b): int => $a['muscleGroup']->position <=> $b['muscleGroup']->position);

        return array_map(
            static fn (array $group): ExerciseGuideMuscleGroup => new ExerciseGuideMuscleGroup($group['muscleGroup']->name, $group['exercises']),
            $groups,
        );
    }

    /**
     * Dans l'ordre du catalogue des fiches ; un exercice retiré du catalogue public est omis.
     *
     * @return list<array{ExerciseGuide, Exercise}>
     */
    private function guidesWithTheirExercise(): array
    {
        $guides = $this->catalog->all();
        $exercisesByName = [];
        foreach ($this->exerciseRepository->findPublicWithMusclesByNames(array_map(static fn (ExerciseGuide $guide): string => $guide->exerciseName(), $guides)) as $exercise) {
            $exercisesByName[$exercise->name] = $exercise;
        }

        $pairs = [];
        foreach ($guides as $guide) {
            if (isset($exercisesByName[$guide->exerciseName()])) {
                $pairs[] = [$guide, $exercisesByName[$guide->exerciseName()]];
            }
        }

        return $pairs;
    }

    /**
     * @return list<MuscleGroup>
     */
    private function primaryMuscleGroups(Exercise $exercise): array
    {
        $muscleGroups = [];
        foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
            if (MuscleTypeEnum::PRIMARY === $exerciseMuscle->type) {
                $muscleGroups[] = $exerciseMuscle->muscleGroup;
            }
        }

        return $muscleGroups;
    }
}
