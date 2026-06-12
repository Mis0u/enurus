<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExerciseCreateService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Persists a new custom exercise owned by the given user.
     *
     * @param Collection<int, ExerciseMuscle> $muscles
     */
    public function create(Exercise $exercise, User $owner, Collection $muscles): void
    {
        $exercise->owner = $owner;

        $this->attachMuscles($exercise, $muscles);

        $this->em->persist($exercise);
        $this->em->flush();
    }

    /**
     * @param Collection<int, ExerciseMuscle> $muscles
     */
    private function attachMuscles(Exercise $exercise, Collection $muscles): void
    {
        foreach ($muscles as $exerciseMuscle) {
            $exerciseMuscle->exercise = $exercise;
            $exercise->exerciseMuscles->add($exerciseMuscle);
            $this->em->persist($exerciseMuscle);
        }
    }
}
