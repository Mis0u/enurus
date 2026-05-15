<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workout;
use App\Repository\WorkoutRepository;
use DateTimeImmutable;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WorkoutHistoryController extends AbstractController
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
    ], name: 'app_workout_history')]
    #[IsGranted('ROLE_USER')]
    public function index(Request $request, WorkoutRepository $workoutRepository, PaginatorInterface $paginator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $filters = $this->resolveFilters($request);
        $filterType = $this->resolveActiveFilter($request);
        $filterDate = $request->query->get('date');

        $pagination = $this->paginate($paginator, $request, $workoutRepository, $user, $filters);

        $workoutIds = $this->extractWorkoutId($pagination);

        $tonnageMap = $workoutRepository->findTonnageByWorkoutIds($workoutIds);
        $musclesMap = $workoutRepository->findMusclesByWorkoutIds($workoutIds);

        $hiddenCountMap = $this->computeHiddenMuscleCountMap($musclesMap);

        return $this->render('workout/history/index.html.twig', [
            'user' => $user,
            'pagination' => $pagination,
            'tonnageMap' => $tonnageMap,
            'musclesMap' => $musclesMap,
            'hiddenCountMap' => $hiddenCountMap,
            'filterType' => $filterType,
            'filterDate' => $filterDate,
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
        ]);
    }

    /**
     * @return array{type?: string, value?: DateTimeImmutable}
     */
    private function resolveFilters(Request $request): array
    {
        $filterDate = $request->query->get('date');
        $filterType = $request->query->get('filter');

        if (null !== $filterDate && '' !== $filterDate) {
            $parsedDate = DateTimeImmutable::createFromFormat('Y-m-d', $filterDate);
            if (false !== $parsedDate) {
                return [
                    'type' => 'date',
                    'value' => $parsedDate,
                ];
            }
        }

        if (in_array($filterType, ['week', 'month'], true)) {
            return [
                'type' => $filterType,
            ];
        }

        return [];
    }

    private function resolveActiveFilter(Request $request): ?string
    {
        $filterDate = $request->query->get('date');
        if (null !== $filterDate && '' !== $filterDate && false !== DateTimeImmutable::createFromFormat('Y-m-d', $filterDate)) {
            return null;
        }

        $filterType = $request->query->get('filter');
        return in_array($filterType, ['week', 'month'], true) ? $filterType : null;
    }

    /**
     * @param array{type?: string, value?: \DateTimeImmutable} $filters
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
