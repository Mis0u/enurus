<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Responsabilité unique : persister une nouvelle Routine avec ses RoutineExercise.
 *
 * Le controller extrait la collection depuis le formulaire,
 * ce service attache les entités et flush.
 */
final readonly class RoutineCreateService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Collection<int, RoutineExercise> $routineExercises
     */
    public function create(Routine $routine, User $owner, Collection $routineExercises): void
    {
        $routine->owner = $owner;

        $this->attachExercises($routine, $routineExercises);

        $this->em->persist($routine);
        $this->em->flush();
    }

    /**
     * @param Collection<int, RoutineExercise> $routineExercises
     */
    private function attachExercises(Routine $routine, Collection $routineExercises): void
    {
        foreach ($routineExercises as $routineExercise) {
            $routineExercise->routine = $routine;
            $routine->routineExercises->add($routineExercise);
        }
    }
}
