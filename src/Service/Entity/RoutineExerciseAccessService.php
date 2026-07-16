<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\RoutineExercise;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;

/**
 * Responsabilité unique : vérifier qu'un utilisateur a le droit d'utiliser chacun des exercices
 * d'une collection de RoutineExercise (exercice public, ou personnel appartenant à l'utilisateur).
 */
final class RoutineExerciseAccessService
{
    /**
     * @param Collection<int, RoutineExercise> $routineExercises
     */
    public function allAccessible(Collection $routineExercises, User $user): bool
    {
        foreach ($routineExercises as $routineExercise) {
            $exercise = $routineExercise->exercise;
            if (! $exercise->isPublic && $exercise->owner !== $user) {
                return false;
            }
        }

        return true;
    }
}
