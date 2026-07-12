<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ExerciseMuscle;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\ExerciseSetRepository;
use App\Repository\WorkoutExerciseRepository;
use App\Repository\WorkoutRepository;
use App\Security\Voter\WorkoutVoter;
use App\Service\Utils\WeightConverterService;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WorkoutShowController extends AbstractController
{
    #[Route(path: [
        'fr' => '/seance/{id}',
        'en' => '/workout/{id}',
        'it' => '/allenamento/{id}',
        'es' => '/entrenamiento/{id}',
        'pt' => '/treino/{id}',
        'de' => '/training/{id}',
        'nl' => '/training/{id}',
        'pl' => '/trening/{id}',
    ], name: 'app_workout_show')]
    #[IsGranted('ROLE_USER')]
    public function __invoke(
        #[MapEntity(id: 'id')]
        Workout $workout,
        WorkoutRepository $workoutRepository,
        WorkoutExerciseRepository $workoutExerciseRepository,
        ExerciseSetRepository $exerciseSetRepository,
        WeightConverterService $weightConverter,
    ): Response {
        $this->denyAccessUnlessGranted(WorkoutVoter::VIEW, $workout);

        /** @var User $user */
        $user = $this->getUser();

        $workoutExercises = $workoutExerciseRepository->findWithExercisesAndSets($workout);
        $workoutExerciseIds = $this->extractWorkoutExerciseIds($workoutExercises);
        $exercises = $this->extractExercises($workoutExercises);

        $tonnageMap = $workoutRepository->findTonnageByWorkoutIds([(string) $workout->id]);
        $exerciseTonnageMap = $workoutExerciseRepository->findTonnageByWorkoutExerciseIds($workoutExerciseIds);
        $prMap = $exerciseSetRepository->findMaxWeightPerExercise($user, $exercises);

        $totalTonnageKg = $tonnageMap[(string) $workout->id] ?? 0.0;
        $totalTonnage = $weightConverter->convertToLbs($totalTonnageKg, $user->unitOfMeasure);
        $exerciseData = $this->buildExerciseData($workoutExercises, $prMap, $exerciseTonnageMap, $weightConverter, $user);
        [$totalSets, $totalReps] = $this->countSetsAndReps($exerciseData);

        return $this->render('workout/show/show.html.twig', [
            'workout' => $workout,
            'exerciseData' => $exerciseData,
            'totalTonnage' => $totalTonnage,
            'totalSets' => $totalSets,
            'totalReps' => $totalReps,
            'unit' => $user->unitOfMeasure,
            'allPrimarySvgIds' => $this->resolveAllPrimarySvgIds($exerciseData),
            'allSecondarySvgIds' => $this->resolveAllSecondarySvgIds($exerciseData),
            'user' => $user,
        ]);
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
     * @param array<string, float> $prMap
     * @param array<string, float> $exerciseTonnageMap
     * @return array<int, array{
     *     workoutExercise: WorkoutExercise,
     *     sets: array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, isPr: bool}>,
     *     tonnage: float,
     *     primarySvgIds: array<string>,
     *     secondarySvgIds: array<string>
     * }>
     */
    private function buildExerciseData(
        array $workoutExercises,
        array $prMap,
        array $exerciseTonnageMap,
        WeightConverterService $weightConverter,
        User $user,
    ): array {
        return array_map(
            fn (WorkoutExercise $we) => $this->buildSingleExerciseData(
                $we,
                $prMap,
                $exerciseTonnageMap,
                $weightConverter,
                $user,
            ),
            $workoutExercises
        );
    }

    /**
     * @param array<string, float> $prMap
     * @param array<string, float> $exerciseTonnageMap
     * @return array{
     *     workoutExercise: WorkoutExercise,
     *     sets: array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, isPr: bool}>,
     *     tonnage: float,
     *     primarySvgIds: array<string>,
     *     secondarySvgIds: array<string>
     * }
     */
    private function buildSingleExerciseData(
        WorkoutExercise $we,
        array $prMap,
        array $exerciseTonnageMap,
        WeightConverterService $weightConverter,
        User $user,
    ): array {
        $prWeight = $prMap[(string) $we->exercise->id] ?? null;
        $tonnage = $weightConverter->convertToLbs(
            $exerciseTonnageMap[(string) $we->id] ?? 0.0,
            $user->unitOfMeasure
        );
        $sets = $this->buildSets($we, $prWeight, $weightConverter, $user);
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
     * @return array<int, array{position: int, weightFormatted: string, reps: int, tonnage: float, tonnageUnit: string, isPr: bool}>
     */
    private function buildSets(
        WorkoutExercise $we,
        ?float $prWeight,
        WeightConverterService $weightConverter,
        User $user,
    ): array {
        $sets = [];

        foreach ($we->exerciseSets as $set) {
            $rawTonnage = $set->weight * $set->reps;

            $sets[] = [
                'position' => $set->position,
                'weightFormatted' => $weightConverter->format($set->weight, $user->unitOfMeasure),
                'reps' => $set->reps,
                'tonnage' => $weightConverter->convertToLbs($rawTonnage, $user->unitOfMeasure),
                'tonnageUnit' => $user->unitOfMeasure->label(),
                'isPr' => null !== $prWeight && $set->weight >= $prWeight,
            ];
        }

        usort($sets, static fn ($a, $b) => $a['position'] <=> $b['position']);

        return $sets;
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
