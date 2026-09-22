<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Utils\WeightConverterService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Calcule la liste paginée des séances d'un utilisateur (`$subject`) telle que la lit `$viewer` :
 * les séances et l'historique de records sont ceux du propriétaire, mais l'unité de poids affichée
 * dépend de celui qui regarde — même principe que `DashboardViewDataBuilder`. Pour sa propre liste,
 * les deux sont le même utilisateur (`WorkoutListController`) ; pour une liste partagée, `$subject`
 * est le propriétaire des séances et `$viewer` l'utilisateur connecté
 * (`ProfileConnectionWorkoutListController`).
 */
final readonly class WorkoutListViewDataBuilder
{
    private const int DISPLAY_MAX_MUSCLE_FOR_MOBILE = 5;

    public function __construct(
        private WorkoutRepository $workoutRepository,
        private WorkoutTonnageRepository $workoutTonnageRepository,
        private WorkoutMuscleRepository $workoutMuscleRepository,
        private WorkoutStatsRepository $workoutStatsRepository,
        private PaginatorInterface $paginator,
        private WeightConverterService $weightConverter,
        private WorkoutRecordDetectionService $workoutRecordDetectionService,
    ) {
    }

    /**
     * @param array{type?: string, value?: \DateTimeImmutable, routine?: 'free'|Routine, muscles?: array<string, string>} $filters
     * @return array{
     *     pagination: PaginationInterface<int, Workout>,
     *     tonnageMap: array<string, float>,
     *     musclesMap: array<string, array<int, array{name: string, type: string}>>,
     *     hiddenCountMap: array<string, int>,
     *     exerciseCountMap: array<string, int>,
     *     hasPrMap: array<string, bool>,
     *     hasRepsRecordMap: array<string, bool>,
     * }
     */
    public function build(User $subject, User $viewer, array $filters, int $page, int $limit): array
    {
        $queryBuilder = $this->workoutRepository->findByUserPaginated($subject, $filters);

        /** @var PaginationInterface<int, Workout> $pagination */
        $pagination = $this->paginator->paginate($queryBuilder, $page, $limit);
        $workoutIds = $this->extractWorkoutId($pagination);

        $tonnageMap = $this->workoutTonnageRepository->findTonnageByWorkoutIds($workoutIds);
        $musclesMap = $this->workoutMuscleRepository->findMusclesByWorkoutIds($workoutIds);

        $tonnageMap = array_map(
            fn (float $tonnage) => $this->weightConverter->convertToLbs($tonnage, $viewer->unitOfMeasure),
            $tonnageMap
        );

        return [
            'pagination' => $pagination,
            'tonnageMap' => $tonnageMap,
            'musclesMap' => $musclesMap,
            'hiddenCountMap' => $this->computeHiddenMuscleCountMap($musclesMap),
            'exerciseCountMap' => $this->workoutStatsRepository->findExerciseCountByWorkoutIds($workoutIds),
            'hasPrMap' => $this->workoutRecordDetectionService->hasPrByWorkoutId($subject, $workoutIds),
            'hasRepsRecordMap' => $this->workoutRecordDetectionService->hasRepsRecordByWorkoutId($subject, $workoutIds),
        ];
    }

    /**
     * @param PaginationInterface<int, Workout> $pagination
     * @return string[]
     */
    private function extractWorkoutId(PaginationInterface $pagination): array
    {
        return array_map(
            static fn (object $w): string => (string) $w->id,
            iterator_to_array($pagination)
        );
    }

    /**
     * @param array<string, array<int, array{name: string, type: string}>> $musclesMap
     * @return array<string, int>
     */
    private function computeHiddenMuscleCountMap(array $musclesMap): array
    {
        $hiddenCountMap = [];
        foreach ($musclesMap as $workoutId => $muscles) {
            $total = count($muscles);
            $hiddenCountMap[$workoutId] = self::DISPLAY_MAX_MUSCLE_FOR_MOBILE < $total ? $total - self::DISPLAY_MAX_MUSCLE_FOR_MOBILE : 0;
        }

        return $hiddenCountMap;
    }
}
