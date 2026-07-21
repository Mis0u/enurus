<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\ExerciseMuscle;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\ExerciseSetRepository;
use App\Repository\WorkoutExerciseRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Utils\WeightConverterService;

/**
 * Responsabilité unique : assembler les données affichées sur la page de détail d'une séance
 * (tonnage, PR, records de reps, SVG des muscles sollicités) à partir du Workout et de son
 * historique. Le controller se contente d'orchestrer l'appel et de rendre la vue.
 */
final readonly class WorkoutShowDataService
{
    public function __construct(
        private WorkoutTonnageRepository $workoutTonnageRepository,
        private WorkoutExerciseRepository $workoutExerciseRepository,
        private ExerciseSetRepository $exerciseSetRepository,
        private WeightConverterService $weightConverter,
    ) {
    }

    /**
     * @return array{
     *     exerciseData: array<int, array{
     *         workoutExercise: WorkoutExercise,
     *         sets: array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, tonnageUnit: string, isPr: bool, isRepsRecord: bool}>,
     *         tonnage: float,
     *         primarySvgIds: array<string>,
     *         secondarySvgIds: array<string>,
     *         muscles: array<ExerciseMuscle>,
     *     }>,
     *     totalTonnage: float,
     *     totalSets: int,
     *     totalReps: int,
     *     allPrimarySvgIds: array<string>,
     *     allSecondarySvgIds: array<string>,
     * }
     */
    public function build(Workout $workout, User $user): array
    {
        $workoutExercises = $this->workoutExerciseRepository->findWithExercisesAndSets($workout);
        $workoutExerciseIds = $this->extractWorkoutExerciseIds($workoutExercises);
        $exercises = $this->extractExercises($workoutExercises);

        $tonnageMap = $this->workoutTonnageRepository->findTonnageByWorkoutIds([(string) $workout->id]);
        $exerciseTonnageMap = $this->workoutExerciseRepository->findTonnageByWorkoutExerciseIds($workoutExerciseIds);
        $priorMaxWeightPerExercise = $this->exerciseSetRepository->findMaxWeightPerExerciseBeforeDate($user, $exercises, $workout->performedAt);
        $priorMaxRepsPerWeight = $this->exerciseSetRepository->findMaxRepsPerWeightBeforeDate($user, $exercises, $workout->performedAt);

        $totalTonnageKg = $tonnageMap[(string) $workout->id] ?? 0.0;
        $totalTonnage = $this->weightConverter->convertToLbs($totalTonnageKg, $user->unitOfMeasure);
        $exerciseData = $this->buildExerciseData($workoutExercises, $priorMaxWeightPerExercise, $priorMaxRepsPerWeight, $exerciseTonnageMap, $user);
        [$totalSets, $totalReps] = $this->countSetsAndReps($exerciseData);

        return [
            'exerciseData' => $exerciseData,
            'totalTonnage' => $totalTonnage,
            'totalSets' => $totalSets,
            'totalReps' => $totalReps,
            'allPrimarySvgIds' => $this->resolveAllPrimarySvgIds($exerciseData),
            'allSecondarySvgIds' => $this->resolveAllSecondarySvgIds($exerciseData),
        ];
    }

    /**
     * @param array<int, array{sets: array<int, array{reps: int}>}> $exerciseData
     * @return array{0: int, 1: int}
     */
    private function countSetsAndReps(array $exerciseData): array
    {
        $totalSets = 0;
        $totalReps = 0;

        foreach ($exerciseData as $item) {
            $totalSets += \count($item['sets']);

            foreach ($item['sets'] as $set) {
                $totalReps += $set['reps'];
            }
        }

        return [$totalSets, $totalReps];
    }

    /**
     * @param array<WorkoutExercise> $workoutExercises
     * @return string[]
     */
    private function extractWorkoutExerciseIds(array $workoutExercises): array
    {
        return array_map(
            static fn (WorkoutExercise $we) => (string) $we->id,
            $workoutExercises
        );
    }

    /**
     * @param array<WorkoutExercise> $workoutExercises
     * @return array<\App\Entity\Exercise>
     */
    private function extractExercises(array $workoutExercises): array
    {
        return array_map(
            static fn (WorkoutExercise $we) => $we->exercise,
            $workoutExercises
        );
    }

    /**
     * @param array<WorkoutExercise> $workoutExercises
     * @param array<string, float> $priorMaxWeightPerExercise
     * @param array<string, array<string, int>> $priorMaxRepsPerWeight
     * @param array<string, float> $exerciseTonnageMap
     * @return array<int, array{
     *     workoutExercise: WorkoutExercise,
     *     sets: array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, tonnageUnit: string, isPr: bool, isRepsRecord: bool}>,
     *     tonnage: float,
     *     primarySvgIds: array<string>,
     *     secondarySvgIds: array<string>,
     *     muscles: array<ExerciseMuscle>,
     * }>
     */
    private function buildExerciseData(
        array $workoutExercises,
        array $priorMaxWeightPerExercise,
        array $priorMaxRepsPerWeight,
        array $exerciseTonnageMap,
        User $user,
    ): array {
        return array_map(
            fn (WorkoutExercise $we) => $this->buildSingleExerciseData(
                $we,
                $priorMaxWeightPerExercise,
                $priorMaxRepsPerWeight,
                $exerciseTonnageMap,
                $user,
            ),
            $workoutExercises
        );
    }

    /**
     * @param array<string, float> $priorMaxWeightPerExercise
     * @param array<string, array<string, int>> $priorMaxRepsPerWeight
     * @param array<string, float> $exerciseTonnageMap
     * @return array{
     *     workoutExercise: WorkoutExercise,
     *     sets: array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, tonnageUnit: string, isPr: bool, isRepsRecord: bool}>,
     *     tonnage: float,
     *     primarySvgIds: array<string>,
     *     secondarySvgIds: array<string>,
     *     muscles: array<ExerciseMuscle>,
     * }
     */
    private function buildSingleExerciseData(
        WorkoutExercise $we,
        array $priorMaxWeightPerExercise,
        array $priorMaxRepsPerWeight,
        array $exerciseTonnageMap,
        User $user,
    ): array {
        $priorMaxWeight = $priorMaxWeightPerExercise[(string) $we->exercise->id] ?? null;
        $priorMaxRepsByWeight = $priorMaxRepsPerWeight[(string) $we->exercise->id] ?? [];
        $tonnage = $this->weightConverter->convertToLbs(
            $exerciseTonnageMap[(string) $we->id] ?? 0.0,
            $user->unitOfMeasure
        );
        $sets = $this->buildSets($we, $priorMaxWeight, $priorMaxRepsByWeight, $user);
        [$primarySvgIds, $secondarySvgIds] = $this->resolveSvgIds($we);
        $sortedMuscles = $this->sortMusclesByType($we->exercise->exerciseMuscles->toArray());

        return [
            'workoutExercise' => $we,
            'sets' => $sets,
            'tonnage' => $tonnage,
            'primarySvgIds' => $primarySvgIds,
            'secondarySvgIds' => $secondarySvgIds,
            'muscles' => $sortedMuscles,
        ];
    }

    /**
     * @param array<ExerciseMuscle> $muscles
     * @return array<ExerciseMuscle>
     */
    private function sortMusclesByType(array $muscles): array
    {
        usort($muscles, static function ($a, $b): int {
            if ($a->type->value === $b->type->value) {
                return 0;
            }
            return 'primary' === $a->type->value ? -1 : 1;
        });

        return $muscles;
    }

    /**
     * Seul le meilleur set de la séance pour cet exercice peut porter le badge PR (poids), et
     * seulement si son poids dépasse strictement le record précédent — une égalité n'est pas un
     * nouveau record battu (même règle que DashboardPrService). Un exercice jamais fait avant
     * (`$priorMaxWeight` null) obtient automatiquement le badge : il n'y a rien à dépasser, le
     * premier essai est le record.
     *
     * Le badge "record de reps" suit la même règle stricte, mais par poids exact plutôt que par
     * exercice, et ne s'applique jamais à un set déjà PR poids ni à un poids jamais fait avant
     * (sans quoi un nouveau poids max déclencherait aussi un record de reps trivial et redondant).
     *
     * @param array<string, int> $priorMaxRepsByWeight poids (chaîne) => reps max avant cette séance
     * @return array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, tonnageUnit: string, isPr: bool, isRepsRecord: bool}>
     */
    private function buildSets(
        WorkoutExercise $we,
        ?float $priorMaxWeight,
        array $priorMaxRepsByWeight,
        User $user,
    ): array {
        $workoutMaxWeight = 0.0;
        /** @var array<string, int> $workoutMaxRepsByWeight */
        $workoutMaxRepsByWeight = [];

        foreach ($we->exerciseSets as $set) {
            $workoutMaxWeight = max($workoutMaxWeight, $set->weight);

            $weightKey = ExerciseSetRepository::weightKey($set->weight);
            $workoutMaxRepsByWeight[$weightKey] = max($workoutMaxRepsByWeight[$weightKey] ?? 0, $set->reps);
        }

        $isWeightPr = $this->beatsRecord($priorMaxWeight, $workoutMaxWeight, firstAttemptCounts: true);

        /** @var array<string, bool> $repsRecordByWeight */
        $repsRecordByWeight = [];
        foreach ($workoutMaxRepsByWeight as $weightKey => $maxReps) {
            $priorMaxReps = $priorMaxRepsByWeight[$weightKey] ?? null;
            $repsRecordByWeight[$weightKey] = $this->beatsRecord(
                null !== $priorMaxReps ? (float) $priorMaxReps : null,
                (float) $maxReps,
                firstAttemptCounts: false,
            );
        }

        $sets = [];

        foreach ($we->exerciseSets as $set) {
            $rawTonnage = $set->weight * $set->reps;
            $weightKey = ExerciseSetRepository::weightKey($set->weight);

            $isPr = $isWeightPr && $set->weight === $workoutMaxWeight;
            $isRepsRecord = ! $isPr
                && ($repsRecordByWeight[$weightKey] ?? false)
                && $set->reps === $workoutMaxRepsByWeight[$weightKey];

            $sets[] = [
                'position' => $set->position,
                'weightFormatted' => $this->weightConverter->format($set->weight, $user->unitOfMeasure),
                'reps' => $set->reps,
                'tonnage' => $this->weightConverter->convertToLbs($rawTonnage, $user->unitOfMeasure),
                'tonnageUnit' => $user->unitOfMeasure->value,
                'isPr' => $isPr,
                'isRepsRecord' => $isRepsRecord,
            ];
        }

        usort($sets, static fn ($a, $b) => $a['position'] <=> $b['position']);

        return $sets;
    }

    /**
     * Un nouveau record est atteint si la valeur dépasse strictement le précédent — une égalité
     * ne compte jamais. `$firstAttemptCounts` décide si l'absence de record précédent (`null`)
     * compte comme un record immédiat : oui pour un poids jamais soulevé, non pour des reps à un
     * poids jamais fait avant (déjà couvert par le PR poids, voir `buildSets`).
     */
    private function beatsRecord(?float $priorMax, float $currentValue, bool $firstAttemptCounts): bool
    {
        return null === $priorMax ? $firstAttemptCounts : $currentValue > $priorMax;
    }

    /**
     * @return array{array<string>, array<string>}
     */
    private function resolveSvgIds(WorkoutExercise $we): array
    {
        $primarySvgIds = [];
        $secondarySvgIds = [];

        foreach ($we->exercise->exerciseMuscles as $em) {
            if ('primary' === $em->type->value) {
                $primarySvgIds = array_merge($primarySvgIds, $em->muscleGroup->svgIds);
            } else {
                $secondarySvgIds = array_merge($secondarySvgIds, $em->muscleGroup->svgIds);
            }
        }

        $primarySvgIds = array_values(array_unique($primarySvgIds));
        $secondarySvgIds = array_values(array_diff(array_unique($secondarySvgIds), $primarySvgIds));

        return [$primarySvgIds, $secondarySvgIds];
    }

    /**
     * @param array<int, array{primarySvgIds: array<string>}> $exerciseData
     * @return array<string>
     */
    private function resolveAllPrimarySvgIds(array $exerciseData): array
    {
        $ids = array_merge(...array_column($exerciseData, 'primarySvgIds'));
        return array_values(array_unique($ids));
    }

    /**
     * @param array<int, array{primarySvgIds: array<string>, secondarySvgIds: array<string>}> $exerciseData
     * @return array<string>
     */
    private function resolveAllSecondarySvgIds(array $exerciseData): array
    {
        $primary = $this->resolveAllPrimarySvgIds($exerciseData);
        $secondary = array_merge(...array_column($exerciseData, 'secondarySvgIds'));
        return array_values(array_diff(array_unique($secondary), $primary));
    }
}
