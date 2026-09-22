<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\Routine;
use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\MuscleGroupRepository;
use App\Repository\RoutineRepository;
use App\Security\Voter\ProfileConnectionVoter;
use App\Service\Entity\MuscleGroupSorterService;
use App\Service\Workout\WorkoutListViewDataBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Liste des séances d'une connexion, en lecture seule — même filtres et pagination que
 * `WorkoutListController`, réutilisés via `WorkoutListViewDataBuilder`. L'identifiant de la route
 * est celui de la `ProfileConnection`, jamais celui du compte affiché.
 */
#[Route(path: [
    'fr' => '/connexions/{id}/seances',
    'en' => '/connections/{id}/workouts',
    'it' => '/connessioni/{id}/allenamenti',
    'es' => '/conexiones/{id}/entrenamientos',
    'pt' => '/conexoes/{id}/treinos',
    'de' => '/verbindungen/{id}/trainings',
    'nl' => '/verbindingen/{id}/trainingen',
    'pl' => '/polaczenia/{id}/treningi',
], name: 'app_profile_connection_workout_list', methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionWorkoutListController extends AbstractController
{
    private const int DISPLAY_LIMIT_BY_DEFAULT = 10;

    private const array DISPLAY_LIMIT_ALLOWED = [10, 25, 50];

    private const int MUSCLE_FILTER_PAIR_PARTS = 2;

    public function __construct(
        private readonly RoutineRepository $routineRepository,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly MuscleGroupSorterService $muscleGroupSorter,
        private readonly WorkoutListViewDataBuilder $viewDataBuilder,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::VIEW_WORKOUTS, subject: 'connection')]
    public function __invoke(ProfileConnection $connection, Request $request): Response
    {
        $viewer = $this->getUser();

        if (! $viewer instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $subject = $connection->counterpartOf($viewer);

        /** @var array<Routine> $routines */
        $routines = $this->routineRepository->findByOwnerOrderedByDate($subject)->getQuery()->getResult();

        $filters = $this->resolveFilters($request, $routines);
        $filterType = in_array($filters['type'] ?? null, ['week', 'month'], true) ? $filters['type'] : null;
        $filterDate = $request->query->get('date');
        $filterRoutine = match (true) {
            'free' === ($filters['routine'] ?? null) => 'free',
            ($filters['routine'] ?? null) instanceof Routine => (string) $filters['routine']->id,
            default => null,
        };

        $filterMuscles = $this->resolveMuscleFilters($request);
        $filters['muscles'] = $filterMuscles;

        $limit = $request->query->getInt('limit', self::DISPLAY_LIMIT_BY_DEFAULT);
        $limit = in_array($limit, self::DISPLAY_LIMIT_ALLOWED, true) ? $limit : self::DISPLAY_LIMIT_BY_DEFAULT;

        $data = $this->viewDataBuilder->build($subject, $viewer, $filters, $request->query->getInt('page', 1), $limit);

        /** @var list<\App\Entity\MuscleGroup> $muscleGroups */
        $muscleGroups = $this->muscleGroupRepository->findAllOrderedByPosition();
        $muscleGroups = $this->muscleGroupSorter->sortByName($muscleGroups, $viewer->locale);

        return $this->render('profile_connection/workout/list.html.twig', [
            'connection' => $connection,
            'subject' => $subject,
            'pagination' => $data['pagination'],
            'tonnageMap' => $data['tonnageMap'],
            'musclesMap' => $data['musclesMap'],
            'hiddenCountMap' => $data['hiddenCountMap'],
            'filterType' => $filterType,
            'filterDate' => $filterDate,
            'routines' => $routines,
            'filterRoutine' => $filterRoutine,
            'muscleGroups' => $muscleGroups,
            'filterMuscles' => $filterMuscles,
            'filterMusclesParam' => $this->encodeMuscleFilters($filterMuscles),
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
            'exerciseCountMap' => $data['exerciseCountMap'],
            'hasPrMap' => $data['hasPrMap'],
            'hasRepsRecordMap' => $data['hasRepsRecordMap'],
        ]);
    }

    /**
     * @param array<Routine> $routines
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

        if (null !== $routineFilter && 'date' !== ($filters['type'] ?? null)) {
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

        return null;
    }

    /**
     * @return array<string, string> groupe musculaire (id) => 'primary'|'secondary'
     */
    private function resolveMuscleFilters(Request $request): array
    {
        $raw = $request->query->get('muscles');

        if (! is_string($raw) || '' === $raw) {
            return [];
        }

        $filters = [];

        foreach (explode(',', $raw) as $pair) {
            $parts = explode(':', $pair, self::MUSCLE_FILTER_PAIR_PARTS);

            if (self::MUSCLE_FILTER_PAIR_PARTS !== count($parts)) {
                continue;
            }

            [$muscleGroupId, $type] = $parts;

            if (! Uuid::isValid($muscleGroupId) || null === MuscleTypeEnum::tryFrom($type)) {
                continue;
            }

            $filters[$muscleGroupId] = $type;
        }

        return $filters;
    }

    /**
     * @param array<string, string> $filterMuscles
     */
    private function encodeMuscleFilters(array $filterMuscles): ?string
    {
        if ([] === $filterMuscles) {
            return null;
        }

        $pairs = [];

        foreach ($filterMuscles as $muscleGroupId => $type) {
            $pairs[] = $muscleGroupId . ':' . $type;
        }

        return implode(',', $pairs);
    }
}
