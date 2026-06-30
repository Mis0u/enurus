<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\RoutineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class RoutineExercisesBlockController extends AbstractController
{
    public function __construct(
        private readonly RoutineRepository $routineRepository,
    ) {
    }

    #[Route('/workout/routine-exercises-block', name: 'workout_routine_exercises_block', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $routineId = $request->query->get('routineId');
        $startIndex = $request->query->getInt('startIndex', 0);

        if (null === $routineId) {
            return new Response('', Response::HTTP_BAD_REQUEST);
        }

        $routine = $this->routineRepository->find($routineId);

        if (null === $routine) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        /** @var User $user */
        $user = $this->getUser();

        if ($routine->owner !== $user) {
            return new Response('', Response::HTTP_FORBIDDEN);
        }

        return $this->render('workout/create/_routine_exercises_block.html.twig', [
            'routineExercises' => $routine->routineExercises,
            'startIndex' => $startIndex,
        ]);
    }
}
