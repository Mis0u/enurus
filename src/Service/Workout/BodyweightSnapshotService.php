<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\User;
use App\Entity\Workout;

/**
 * Fige `ExerciseSet::bodyweightSnapshotKg` sur les séries nouvellement créées d'un exercice
 * "au poids de corps" (`Exercise::bodyweightPercent` non-null) — jamais réécrit sur une série déjà
 * persistée (édition), pour ne jamais faire bouger rétroactivement le tonnage/PR d'une séance
 * passée si l'utilisateur met à jour son poids en réglages. Appelé après `isValid()` et après
 * `WeightConverterService::convertWorkoutSetsToKg()` (le lest doit déjà être en kg), avant `flush()`.
 */
final class BodyweightSnapshotService
{
    private const float PERCENT_SCALE = 100.0;

    public function apply(Workout $workout, User $user): void
    {
        foreach ($workout->workoutExercises as $workoutExercise) {
            if (null === $workoutExercise->exercise->bodyweightPercent) {
                continue;
            }

            foreach ($workoutExercise->exerciseSets as $exerciseSet) {
                if (null !== $exerciseSet->id || null !== $exerciseSet->bodyweightSnapshotKg) {
                    continue;
                }

                $exerciseSet->bodyweightSnapshotKg = $user->bodyweightKg;
            }
        }
    }

    /**
     * Part de poids de corps (kg) pour un exercice au poids de corps — 0.0 si l'exercice n'est
     * pas concerné (`$bodyweightPercent` null) ou si aucun poids de corps n'est disponible
     * (série créée avant l'introduction du snapshot, ou utilisateur sans poids renseigné).
     * Formule centralisée : réutilisée par `WorkoutShowDataService::effectiveWeight()`,
     * `WorkoutEditController::buildExerciseData()` et l'indicateur "poids soulevé" en formulaire.
     */
    public function shareKg(?float $bodyweightKg, ?float $bodyweightPercent): float
    {
        if (null === $bodyweightKg || null === $bodyweightPercent) {
            return 0.0;
        }

        return $bodyweightKg * $bodyweightPercent / self::PERCENT_SCALE;
    }

    /**
     * Détecte une soumission qui ajouterait une série sur un exercice bodyweight sans qu'aucun
     * poids de corps ne soit renseigné — cas possible si l'utilisateur supprime son poids en
     * réglages puis revient éditer une séance existante contenant déjà cet exercice (le seul
     * garde-fou côté sélection d'exercice, `ExerciseSelectorComponent::selectExercise()`, ne
     * couvre pas l'ajout d'une série sur un exercice déjà présent). Sans ce contrôle, `apply()`
     * figerait silencieusement `bodyweightSnapshotKg = null` sur la nouvelle série, faussant son
     * tonnage/PR par rapport aux autres séries du même exercice. Doit être appelé avant `apply()`.
     */
    public function hasNewSetWithoutBodyweight(Workout $workout, User $user): bool
    {
        if (null !== $user->bodyweightKg) {
            return false;
        }

        foreach ($workout->workoutExercises as $workoutExercise) {
            if (null === $workoutExercise->exercise->bodyweightPercent) {
                continue;
            }

            foreach ($workoutExercise->exerciseSets as $exerciseSet) {
                if (null === $exerciseSet->id) {
                    return true;
                }
            }
        }

        return false;
    }
}
