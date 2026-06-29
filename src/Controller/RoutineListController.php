<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\RoutineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
    public function __construct(
        private readonly RoutineRepository $routineRepository,
    ) {
    }

    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $routines = $this->routineRepository->findByOwnerOrderedByDate($user);

        return $this->render('routine/history/index.html.twig', [
            'routines' => $routines,
            'count' => count($routines),
        ]);
    }
}
