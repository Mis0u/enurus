<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\ExerciseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ExerciseBlockController extends AbstractController
{
    #[Route('/workout/exercise-block', name: 'workout_exercise_block', methods: ['GET'])]
    public function __invoke(Request $request, ExerciseRepository $exerciseRepository): Response
    {
        $exerciseId = $request->query->get('exerciseId');
        $index = $request->query->getInt('index', 0);

        $exercise = $exerciseRepository->find($exerciseId);

        if (null === $exercise) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        return $this->render('workout/create/_exercise_card.html.twig', [
            'exercise' => $exercise,
            'index' => $index,
        ]);
    }
}
