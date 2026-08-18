<?php

declare(strict_types=1);

namespace App\Controller\Routine;

use App\Entity\User;
use App\Repository\RoutineRepository;
use App\Service\Utils\WeightConverterService;
use App\Service\Workout\BodyweightSnapshotService;
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
        private readonly WeightConverterService $weightConverter,
        private readonly BodyweightSnapshotService $bodyweightSnapshotService,
    ) {
    }

    #[Route(path: [
        'fr' => '/enregistre-seance/bloc-exercices-routine',
        'en' => '/log-workout/routine-exercises-block',
        'it' => '/registra-allenamento/blocco-esercizi-routine',
        'es' => '/registrar-entrenamiento/bloque-ejercicios-rutina',
        'pt' => '/registar-treino/bloco-exercicios-rotina',
        'de' => '/training-erfassen/routine-uebungen-block',
        'nl' => '/training-vastleggen/routine-oefeningen-blok',
        'pl' => '/zapisz-trening/blok-cwiczen-planu',
    ], name: 'workout_routine_exercises_block', methods: ['GET'])]
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

        $cardBodyweightShares = [];
        foreach ($routine->routineExercises as $routineExercise) {
            $exercise = $routineExercise->exercise;

            if (null === $exercise->bodyweightPercent) {
                continue;
            }

            $shareKg = $this->bodyweightSnapshotService->shareKg($user->bodyweightKg, $exercise->bodyweightPercent);
            $cardBodyweightShares[(string) $exercise->id] = round($this->weightConverter->convertToLbs($shareKg, $user->unitOfMeasure), 1);
        }

        return $this->render('workout/create/_routine_exercises_block.html.twig', [
            'routineExercises' => $routine->routineExercises,
            'startIndex' => $startIndex,
            'cardBodyweightShares' => $cardBodyweightShares,
            'userHasBodyweight' => null !== $user->bodyweightKg,
        ]);
    }
}
