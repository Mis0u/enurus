<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ExerciseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[IsGranted('ROLE_USER')]
class WorkoutEditExerciseBlockController extends AbstractController
{
    #[Route(
        path: '/workout/edit/exercise-block',
        name: 'workout_edit_exercise_block',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(
        Request $request,
        ExerciseRepository $exerciseRepository,
    ): Response {
        $exerciseId = $request->query->get('exerciseId');

        if (! $exerciseId || ! Uuid::isValid($exerciseId)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $exercise = $exerciseRepository->find($exerciseId);
        $index = $request->query->getInt('index', 0);

        if (null === $exercise) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return $this->render('workout/edit/_exercise_card_new.html.twig', [
            'exercise' => $exercise,
            'index' => $index,
        ]);
    }
}
