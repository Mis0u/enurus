<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Repository\RoutineExerciseRepository;
use App\Repository\WorkoutExerciseRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprimer un exercice perso utilisé ailleurs violerait la contrainte FK NOT NULL de
 * WorkoutExercise/RoutineExercise (cf. incident sur la suppression de routine, même famille de
 * bug). Contrairement à Workout->Routine, on ne peut pas mettre l'exercice à NULL sur un
 * WorkoutExercise/RoutineExercise : une série loguée (ExerciseSet) n'a de sens que rattachée à un
 * exercice nommé.
 *
 * Trois cas :
 *  - jamais utilisé            → suppression réelle
 *  - utilisé dans des routines uniquement (jamais loguée en vraie séance) → détaché des routines
 *    (simples gabarits, aucune donnée historique) puis suppression réelle
 *  - utilisé dans au moins une séance loguée → archivage plutôt que suppression, quel que soit le
 *    nombre de séances concernées (l'historique ne doit jamais être modifié rétroactivement)
 */
final readonly class ExerciseDeletionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private WorkoutExerciseRepository $workoutExerciseRepository,
        private RoutineExerciseRepository $routineExerciseRepository,
    ) {
    }

    /**
     * @return bool true si l'exercice a été archivé plutôt que réellement supprimé
     */
    public function delete(Exercise $exercise): bool
    {
        if (0 < $this->workoutExerciseRepository->countByExercise($exercise)) {
            $exercise->archived = true;
            $this->em->flush();

            return true;
        }

        foreach ($this->routineExerciseRepository->findByExercise($exercise) as $routineExercise) {
            $this->em->remove($routineExercise);
        }

        $this->em->remove($exercise);
        $this->em->flush();

        return false;
    }
}
