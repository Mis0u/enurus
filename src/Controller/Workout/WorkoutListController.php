<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\RoutineRepository;
use App\Repository\WorkoutMuscleRepository;
use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Repository\WorkoutTonnageRepository;
use App\Service\Utils\WeightConverterService;
use App\Service\Workout\WorkoutRecordDetectionService;
use DateTimeImmutable;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WorkoutListController extends AbstractController
{
    private const int DISPLAY_LIMIT_BY_DEFAULT = 10;

    private const array DISPLAY_LIMIT_ALLOWED = [10, 25, 50];

    private const int DISPLAY_MAX_MUSCLE_FOR_MOBILE = 5;

    #[Route(path: [
        'fr' => '/mes-seances',
        'en' => '/my-workouts',
        'it' => '/i-miei-allenamenti',
        'es' => '/mis-entrenamientos',
        'pt' => '/os-meus-treinos',
        'de' => '/meine-trainings',
        'nl' => '/mijn-trainingen',
        'pl' => '/moje-treningi',
    ], name: 'app_workout_list')]
    #[IsGranted('ROLE_USER')]
    public function index(
        Request $request,
        WorkoutRepository $workoutRepository,
        WorkoutTonnageRepository $workoutTonnageRepository,
        WorkoutMuscleRepository $workoutMuscleRepository,
        WorkoutStatsRepository $workoutStatsRepository,
        RoutineRepository $routineRepository,
        PaginatorInterface $paginator,
        WeightConverterService $weightConverter,
        WorkoutRecordDetectionService $workoutRecordDetectionService,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();

        /** @var array<Routine> $routines */
        $routines = $routineRepository->findByOwnerOrderedByDate($user)->getQuery()->getResult();

        $filters = $this->resolveFilters($request, $routines);
        $filterType = in_array($filters['type'] ?? null, ['week', 'month'], true) ? $filters['type'] : null;
        $filterDate = $request->query->get('date');
        // Dérivé du filtre résolu (pas de la valeur brute de la query string) : une routine
        // inconnue ou appartenant à un autre utilisateur est ignorée, le select ne doit alors
        // afficher aucune sélection plutôt que refléter une valeur invalide.
        $filterRoutine = match (true) {
            'free' === ($filters['routine'] ?? null) => 'free',
            ($filters['routine'] ?? null) instanceof Routine => (string) $filters['routine']->id,
            default => null,
        };

        $pagination = $this->paginate($paginator, $request, $workoutRepository, $user, $filters);

        $workoutIds = $this->extractWorkoutId($pagination);

        $tonnageMap = $workoutTonnageRepository->findTonnageByWorkoutIds($workoutIds);
        $musclesMap = $workoutMuscleRepository->findMusclesByWorkoutIds($workoutIds);

        $tonnageMap = array_map(
            fn (float $tonnage) => $weightConverter->convertToLbs($tonnage, $user->unitOfMeasure),
            $tonnageMap
        );

        $hiddenCountMap = $this->computeHiddenMuscleCountMap($musclesMap);
        $exerciseCountMap = $workoutStatsRepository->findExerciseCountByWorkoutIds($workoutIds);
        $hasPrMap = $workoutRecordDetectionService->hasPrByWorkoutId($user, $workoutIds);
        $hasRepsRecordMap = $workoutRecordDetectionService->hasRepsRecordByWorkoutId($user, $workoutIds);

        return $this->render('workout/list/index.html.twig', [
            'user' => $user,
            'pagination' => $pagination,
            'tonnageMap' => $tonnageMap,
            'musclesMap' => $musclesMap,
            'hiddenCountMap' => $hiddenCountMap,
            'filterType' => $filterType,
            'filterDate' => $filterDate,
            'routines' => $routines,
            'filterRoutine' => $filterRoutine,
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
            'exerciseCountMap' => $exerciseCountMap,
            'hasPrMap' => $hasPrMap,
            'hasRepsRecordMap' => $hasRepsRecordMap,
        ]);
    }

    /**
     * @param array<Routine> $routines routines de l'utilisateur, pour résoudre/valider le filtre
     * @return array{type?: string, value?: DateTimeImmutable, routine?: 'free'|Routine}
     */
    private function resolveFilters(Request $request, array $routines): array
    {
        $filters = [];

        $filterDate = $request->query->get('date');
        $filterType = $request->query->get('filter');

        if (null !== $filterDate && '' !== $filterDate) {
            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $filterDate);
            if (false !== $parsedDate) {
                $filters['type'] = 'date';
                $filters['value'] = $parsedDate;
            }
        }

        if (! isset($filters['type']) && in_array($filterType, ['week', 'month'], true)) {
            $filters['type'] = $filterType;
        }

        $routineFilter = $this->resolveRoutineFilter($request, $routines);

        if (null !== $routineFilter) {
            $filters['routine'] = $routineFilter;
        }

        return $filters;
    }

    /**
     * @param array<Routine> $routines
     * @return 'free'|Routine|null
     */
    private function resolveRoutineFilter(Request $request, array $routines): string|Routine|null
    {
        $routineParam = $request->query->get('routine');

        if ('free' === $routineParam) {
            return 'free';
        }

        if (null === $routineParam || '' === $routineParam) {
            return null;
        }

        foreach ($routines as $routine) {
            if ((string) $routine->id === $routineParam) {
                return $routine;
            }
        }

        // Routine inconnue ou n'appartenant pas à l'utilisateur : filtre silencieusement ignoré,
        // même logique que pour un filtre de période inconnu.
        return null;
    }

    /**
     * @param array{type?: string, value?: \DateTimeImmutable, routine?: 'free'|Routine} $filters
     * @return PaginationInterface<int, Workout>
     */
    private function paginate(
        PaginatorInterface $paginator,
        Request $request,
        WorkoutRepository $workoutRepository,
        User $user,
        array $filters
    ): PaginationInterface {
        $limit = $request->query->getInt('limit', self::DISPLAY_LIMIT_BY_DEFAULT);
        $limit = in_array($limit, self::DISPLAY_LIMIT_ALLOWED, true) ? $limit : self::DISPLAY_LIMIT_BY_DEFAULT;

        $queryBuilder = $workoutRepository->findByUserPaginated($user, $filters);

        /** @var PaginationInterface<int, Workout> $pagination */
        $pagination = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            $limit
        );

        return $pagination;
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
