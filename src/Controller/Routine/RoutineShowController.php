<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Entity\Routine;
use App\Entity\User;
use App\Security\Voter\RoutineVoter;
use App\Service\Routine\RoutineShowDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: [
    'fr' => '/mes-routines/{id}',
    'en' => '/my-routines/{id}',
    'it' => '/le-mie-routine/{id}',
    'es' => '/mis-rutinas/{id}',
    'pt' => '/as-minhas-rotinas/{id}',
    'de' => '/meine-routinen/{id}',
    'nl' => '/mijn-routines/{id}',
    'pl' => '/moje-plany/{id}',
], name: 'app_routine_show', requirements: [
    'id' => Requirement::UUID_V7,
], methods: ['GET'])]
#[IsGranted('ROLE_USER')]
final class RoutineShowController extends AbstractController
{
    public function __invoke(Routine $routine, RoutineShowDataService $routineShowDataService): Response
    {
        $this->denyAccessUnlessGranted(RoutineVoter::VIEW, $routine);

        /** @var User $user */
        $user = $this->getUser();

        $data = $routineShowDataService->build($routine, $user);

        return $this->render('routine/show/index.html.twig', [
            'routine' => $routine,
            'user' => $user,
            ...$data,
        ]);
    }
}
