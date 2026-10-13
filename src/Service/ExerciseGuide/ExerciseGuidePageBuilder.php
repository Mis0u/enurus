<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\ExerciseRepository;
use App\Service\ExerciseGuide\View\ExerciseGuideLink;
use App\Service\ExerciseGuide\View\ExerciseGuidePage;

/**
 * Fiche publique d'un exercice : l'exercice et ses variantes chargés en une requête.
 */
final readonly class ExerciseGuidePageBuilder
{
    public function __construct(
        private ExerciseRepository $exerciseRepository,
        private ExerciseGuideCatalog $catalog,
        private ExerciseGuideSlugger $slugger,
    ) {
    }

    /**
     * @return ExerciseGuidePage|null null si l'exercice n'est plus au catalogue public
     */
    public function build(ExerciseGuide $guide, string $locale): ?ExerciseGuidePage
    {
        $exercisesByName = $this->exercisesByName($guide);
        $exercise = $exercisesByName[$guide->exerciseName()] ?? null;
        if (null === $exercise) {
            return null;
        }

        $primaryMuscles = $this->musclesOfType($exercise, MuscleTypeEnum::PRIMARY);
        $secondaryMuscles = $this->musclesOfType($exercise, MuscleTypeEnum::SECONDARY);

        return new ExerciseGuidePage(
            guide: $guide,
            exerciseId: (string) $exercise->id,
            nameKey: $exercise->name,
            primaryMuscleKeys: $this->muscleKeys($primaryMuscles),
            secondaryMuscleKeys: $this->muscleKeys($secondaryMuscles),
            primarySvgIds: $this->svgIds($primaryMuscles),
            secondarySvgIds: $this->svgIds($secondaryMuscles),
            variants: $this->variants($guide, $exercisesByName, $locale),
        );
    }

    /**
     * @return array<string, Exercise>
     */
    private function exercisesByName(ExerciseGuide $guide): array
    {
        $names = [
            $guide->exerciseName(),
            ...array_map(static fn (string $key): string => $key . '.name', $guide->variantKeys),
        ];

        $exercisesByName = [];
        foreach ($this->exerciseRepository->findPublicWithMusclesByNames($names) as $exercise) {
            $exercisesByName[$exercise->name] = $exercise;
        }

        return $exercisesByName;
    }

    /**
     * Variante retirée du catalogue public : simplement omise.
     *
     * @param array<string, Exercise> $exercisesByName
     *
     * @return list<ExerciseGuideLink>
     */
    private function variants(ExerciseGuide $guide, array $exercisesByName, string $locale): array
    {
        $variants = [];
        foreach ($guide->variantKeys as $variantKey) {
            if (! isset($exercisesByName[$variantKey . '.name'])) {
                continue;
            }

            $variantGuide = $this->catalog->find($variantKey);
            $variants[] = new ExerciseGuideLink(
                nameKey: $variantKey . '.name',
                slug: null === $variantGuide ? null : $this->slugger->slugFor($variantGuide, $locale),
                reasonKey: $guide->variantReasonKey($variantKey),
            );
        }

        return $variants;
    }

    /**
     * @return list<ExerciseMuscle> dans l'ordre des groupes musculaires
     */
    private function musclesOfType(Exercise $exercise, MuscleTypeEnum $type): array
    {
        $muscles = array_values(array_filter(
            $exercise->exerciseMuscles->toArray(),
            static fn (ExerciseMuscle $muscle): bool => $muscle->type === $type,
        ));
        usort($muscles, static fn (ExerciseMuscle $a, ExerciseMuscle $b): int => $a->muscleGroup->position <=> $b->muscleGroup->position);

        return $muscles;
    }

    /**
     * @param list<ExerciseMuscle> $muscles
     *
     * @return list<string>
     */
    private function muscleKeys(array $muscles): array
    {
        return array_map(static fn (ExerciseMuscle $muscle): string => $muscle->muscleGroup->name, $muscles);
    }

    /**
     * @param list<ExerciseMuscle> $muscles
     *
     * @return list<string>
     */
    private function svgIds(array $muscles): array
    {
        return array_values(array_unique(array_merge(...array_map(
            static fn (ExerciseMuscle $muscle): array => $muscle->muscleGroup->svgIds,
            $muscles,
        ))));
    }
}
