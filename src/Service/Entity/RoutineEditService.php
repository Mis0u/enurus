<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Routine;
use App\Entity\RoutineExercise;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Responsabilité unique : mettre à jour une Routine existante avec ses nouveaux RoutineExercise.
 *
 * Stratégie : suppression + réinsertion de la collection complète.
 * orphanRemoval: true sur Routine::$routineExercises gère la suppression en base.
 */
final readonly class RoutineEditService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Collection<int, RoutineExercise> $newExercises
     */
    public function update(Routine $routine, Collection $newExercises): void
    {
        $this->clearExercises($routine);
        $this->em->flush(); // Force la suppression avant réinsertion
        $this->attachExercises($routine, $newExercises);
        $this->em->flush();
    }

    private function clearExercises(Routine $routine): void
    {
        foreach ($routine->routineExercises as $routineExercise) {
            $this->em->remove($routineExercise);
        }

        $routine->routineExercises->clear();
    }

    /**
     * @param Collection<int, RoutineExercise> $newExercises
     */
    private function attachExercises(Routine $routine, Collection $newExercises): void
    {
        foreach ($newExercises as $routineExercise) {
            $routineExercise->routine = $routine;
            $routine->routineExercises->add($routineExercise);
            $this->em->persist($routineExercise);
        }
    }
}
