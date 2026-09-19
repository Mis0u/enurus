<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Entity\Routine;
use App\Entity\User;
use App\Repository\RoutineRepository;
use App\Service\Workout\WorkoutListViewDataBuilder;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class WorkoutListController extends AbstractController
{
    private const int DISPLAY_LIMIT_BY_DEFAULT = 10;

    private const array DISPLAY_LIMIT_ALLOWED = [10, 25, 50];

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
        RoutineRepository $routineRepository,
        WorkoutListViewDataBuilder $viewDataBuilder,
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

        $limit = $request->query->getInt('limit', self::DISPLAY_LIMIT_BY_DEFAULT);
        $limit = in_array($limit, self::DISPLAY_LIMIT_ALLOWED, true) ? $limit : self::DISPLAY_LIMIT_BY_DEFAULT;

        $data = $viewDataBuilder->build($user, $user, $filters, $request->query->getInt('page', 1), $limit);

        return $this->render('workout/list/index.html.twig', [
            'user' => $user,
            'pagination' => $data['pagination'],
            'tonnageMap' => $data['tonnageMap'],
            'musclesMap' => $data['musclesMap'],
            'hiddenCountMap' => $data['hiddenCountMap'],
            'filterType' => $filterType,
            'filterDate' => $filterDate,
            'routines' => $routines,
            'filterRoutine' => $filterRoutine,
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
            'exerciseCountMap' => $data['exerciseCountMap'],
            'hasPrMap' => $data['hasPrMap'],
            'hasRepsRecordMap' => $data['hasRepsRecordMap'],
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

        // Le filtre date est exclusif : combiné à une routine, il n'apporte rien puisqu'une
        // journée ne contient jamais qu'une poignée de séances. semaine/mois restent combinables.
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

        // Routine inconnue ou n'appartenant pas à l'utilisateur : filtre silencieusement ignoré,
        // même logique que pour un filtre de période inconnu.
        return null;
    }
}
