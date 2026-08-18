<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Entity\Routine;
use App\Entity\User;
use App\Repository\RoutineRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
#[Route(path: [
    'fr' => '/mes-routines',
    'en' => '/my-routines',
    'it' => '/le-mie-routine',
    'es' => '/mis-rutinas',
    'pt' => '/as-minhas-rotinas',
    'de' => '/meine-routinen',
    'nl' => '/mijn-routines',
    'pl' => '/moje-plany',
], name: 'app_routine_list', methods: ['GET'])]
final class RoutineListController extends AbstractController
{
    private const int DISPLAY_LIMIT_BY_DEFAULT = 10;

    private const array DISPLAY_LIMIT_ALLOWED = [10, 25, 50];

    public function __construct(
        private readonly RoutineRepository $routineRepository,
    ) {
    }

    public function __invoke(Request $request, PaginatorInterface $paginator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = $request->query->getInt('limit', self::DISPLAY_LIMIT_BY_DEFAULT);
        $limit = in_array($limit, self::DISPLAY_LIMIT_ALLOWED, true) ? $limit : self::DISPLAY_LIMIT_BY_DEFAULT;

        /** @var PaginationInterface<int, Routine> $pagination */
        $pagination = $paginator->paginate(
            $this->routineRepository->findByOwnerOrderedByDate($user),
            $request->query->getInt('page', 1),
            $limit
        );

        return $this->render('routine/list/index.html.twig', [
            'pagination' => $pagination,
            'count' => $pagination->getTotalItemCount(),
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
            'bodyweightWarnings' => $this->bodyweightWarnings($pagination, $user),
        ]);
    }

    /**
     * @param PaginationInterface<int, Routine> $pagination
     * @return array<string, bool>
     */
    private function bodyweightWarnings(PaginationInterface $pagination, User $user): array
    {
        if (null !== $user->bodyweightKg) {
            return [];
        }

        $warnings = [];
        foreach ($pagination as $routine) {
            foreach ($routine->routineExercises as $routineExercise) {
                if (null !== $routineExercise->exercise->bodyweightPercent) {
                    $warnings[(string) $routine->id] = true;
                    break;
                }
            }
        }

        return $warnings;
    }
}
