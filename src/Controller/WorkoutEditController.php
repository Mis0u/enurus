<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Form\WorkoutType;
use App\Repository\WorkoutExerciseRepository;
use App\Security\Voter\WorkoutVoter;
use App\Service\Utils\WeightConverterService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
class WorkoutEditController extends AbstractController
{
    #[Route(
        path: [
            'fr' => '/seance/{id}/modifier',
            'en' => '/workout/{id}/edit',
            'it' => '/allenamento/{id}/modifica',
            'es' => '/entrenamiento/{id}/editar',
            'pt' => '/treino/{id}/editar',
            'de' => '/training/{id}/bearbeiten',
            'nl' => '/training/{id}/bewerken',
            'pl' => '/trening/{id}/edytuj',
        ],
        name: 'app_workout_edit',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function __invoke(
        #[MapEntity(mapping: [
            'id' => 'id',
        ])]
        Workout $workout,
        Request $request,
        EntityManagerInterface $em,
        WorkoutExerciseRepository $workoutExerciseRepository,
        WeightConverterService $weightConverter,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(WorkoutVoter::EDIT, $workout);

        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(WorkoutType::class, $workout);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', $translator->trans('workout.created', [], 'navigation'));
            $weightConverter->convertWorkoutSetsToKg($workout, $user->unitOfMeasure);
            $em->flush();

            return $this->redirectToRoute('app_workout_show', [
                'id' => $workout->id,
                '_locale' => $request->getLocale(),
            ]);
        }

        if ($form->isSubmitted() && ! $form->isValid()) {
            $this->addFlash('error', $translator->trans('workout.error.validation', [], 'navigation'));

            return $this->redirectToRoute('app_workout_edit', [
                'id' => $workout->id,
                '_locale' => $request->getLocale(),
            ]);
        }

        $workoutExercises = $workoutExerciseRepository->findWithExercisesAndSets($workout);
        $exerciseData = $this->buildExerciseData($workoutExercises, $user, $weightConverter);

        return $this->render('workout/edit/edit.html.twig', [
            'workout' => $workout,
            'form' => $form,
            'exerciseData' => $exerciseData,
            'unit' => $user->unitOfMeasure,
            'user' => $user,
        ]);
    }

    /**
     * @param  array<int, WorkoutExercise> $workoutExercises
     * @return array<int, array{
     *     workoutExercise: WorkoutExercise,
     *     sets: array<int, array{position: int, weight: float, reps: int}>,
     *     tonnage: float,
     * }>
     */
    private function buildExerciseData(
        array $workoutExercises,
        User $user,
        WeightConverterService $weightConverter,
    ): array {
        $data = [];

        foreach ($workoutExercises as $workoutExercise) {
            $sets = [];
            $tonnage = 0.0;

            foreach ($workoutExercise->exerciseSets as $set) {
                $sets[] = [
                    'position' => $set->position,
                    'weight' => $weightConverter->convertToLbs($set->weight, $user->unitOfMeasure),
                    'reps' => $set->reps,
                ];
                $tonnage += $set->weight * $set->reps;
            }

            $data[] = [
                'workoutExercise' => $workoutExercise,
                'sets' => $sets,
                'tonnage' => $weightConverter->convertToLbs($tonnage, $user->unitOfMeasure),
            ];
        }

        return $data;
    }
}
