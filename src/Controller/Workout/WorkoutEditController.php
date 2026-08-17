<?php

declare(strict_types=1);

namespace App\Controller\Workout;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Form\WorkoutType;
use App\Repository\WorkoutExerciseRepository;
use App\Security\Voter\WorkoutVoter;
use App\Service\Utils\WeightConverterService;
use App\Service\Workout\BodyweightSnapshotService;
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
        BodyweightSnapshotService $bodyweightSnapshotService,
        TranslatorInterface $translator,
    ): Response {
        $this->denyAccessUnlessGranted(WorkoutVoter::EDIT, $workout);

        /** @var User $user */
        $user = $this->getUser();

        $originalPerformedAt = $workout->performedAt;
        $previousWeightsById = $this->snapshotWeightsById($workout);

        $form = $this->createForm(WorkoutType::class, $workout, [
            'is_edit' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $bodyweightSnapshotService->hasNewSetWithoutBodyweight($workout, $user)) {
            $this->addFlash('error', $translator->trans('workout.search_exercise.bodyweight_required', [], 'navigation'));

            return $this->redirectToRoute('app_workout_edit', [
                'id' => $workout->id,
                '_locale' => $request->getLocale(),
            ]);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $this->addFlash('success', $translator->trans('workout.flash.updated', [], 'navigation'));
            // `DateType` reconstruit performedAt à minuit (pas de sélecteur d'heure) — on restaure
            // l'heure d'origine sur la date (potentiellement modifiée) pour qu'éditer une séance ne
            // change jamais son tri parmi les séances du même jour.
            $workout->performedAt = $workout->performedAt->setTime(
                (int) $originalPerformedAt->format('H'),
                (int) $originalPerformedAt->format('i'),
                (int) $originalPerformedAt->format('s'),
            );
            $weightConverter->convertWorkoutSetsToKg($workout, $user->unitOfMeasure, $previousWeightsById);
            $bodyweightSnapshotService->apply($workout, $user);
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
        $exerciseData = $this->buildExerciseData($workoutExercises, $user, $weightConverter, $bodyweightSnapshotService);
        $totalTonnage = array_sum(array_column($exerciseData, 'tonnage'));

        return $this->render('workout/edit/edit.html.twig', [
            'workout' => $workout,
            'form' => $form,
            'exerciseData' => $exerciseData,
            'totalTonnage' => $totalTonnage,
            'unit' => $user->unitOfMeasure,
            'user' => $user,
            'imageMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
            'imageAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
        ]);
    }

    /**
     * Capture, avant `$form->handleRequest()`, le poids (déjà en kg) de chaque série existante —
     * voir `WeightConverterService::convertWorkoutSetsToKg()` pour l'usage.
     *
     * @return array<string, float>
     */
    private function snapshotWeightsById(Workout $workout): array
    {
        $previousWeightsById = [];

        foreach ($workout->workoutExercises as $workoutExercise) {
            foreach ($workoutExercise->exerciseSets as $set) {
                if (null !== $set->id) {
                    $previousWeightsById[(string) $set->id] = $set->weight;
                }
            }
        }

        return $previousWeightsById;
    }

    /**
     * @param  array<int, WorkoutExercise> $workoutExercises
     * @return array<int, array{
     *     workoutExercise: WorkoutExercise,
     *     sets: array<int, array{position: int, weight: float, reps: int, duration: ?int, distance: ?int, bodyweightShare: ?float}>,
     *     tonnage: float,
     *     cardBodyweightShare: ?float,
     * }>
     */
    private function buildExerciseData(
        array $workoutExercises,
        User $user,
        WeightConverterService $weightConverter,
        BodyweightSnapshotService $bodyweightSnapshotService,
    ): array {
        $data = [];

        foreach ($workoutExercises as $workoutExercise) {
            $sets = [];
            $tonnage = 0.0;

            $isWeightReps = MeasurementType::WEIGHT_REPS === $workoutExercise->exercise->measurementType;
            $bodyweightPercent = $workoutExercise->exercise->bodyweightPercent;

            foreach ($workoutExercise->exerciseSets as $set) {
                $shareKg = $bodyweightSnapshotService->shareKg($set->bodyweightSnapshotKg, $bodyweightPercent);
                $effectiveWeight = $shareKg + $set->weight;

                $sets[] = [
                    'position' => $set->position,
                    'weight' => $weightConverter->convertToLbs($set->weight, $user->unitOfMeasure),
                    'reps' => $set->reps,
                    'duration' => $set->duration,
                    'distance' => $set->distance,
                    'bodyweightShare' => null !== $bodyweightPercent ? round($weightConverter->convertToLbs($shareKg, $user->unitOfMeasure), 1) : null,
                ];
                // Poids × reps pour un exercice classique, poids seul (comme une série à 1 rep)
                // pour un exercice `TIME`/`DISTANCE` — voir WorkoutTonnageRepository::TONNAGE_SUBQUERY_DQL.
                $tonnage += $isWeightReps ? $effectiveWeight * $set->reps : $effectiveWeight;
            }

            // Part de poids de corps "live" (poids de corps actuel, pas figé) utilisée par
            // l'indicateur "poids soulevé" pour une série ajoutée en JS sur cet exercice — aucune
            // série n'a encore de `bodyweightSnapshotKg` à ce stade.
            $cardShareKg = $bodyweightSnapshotService->shareKg($user->bodyweightKg, $bodyweightPercent);

            $data[] = [
                'workoutExercise' => $workoutExercise,
                'sets' => $sets,
                'tonnage' => $weightConverter->convertToLbs($tonnage, $user->unitOfMeasure),
                'cardBodyweightShare' => null !== $bodyweightPercent ? round($weightConverter->convertToLbs($cardShareKg, $user->unitOfMeasure), 1) : null,
            ];
        }

        return $data;
    }
}
