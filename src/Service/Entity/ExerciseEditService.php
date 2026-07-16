<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExerciseEditService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ExerciseMuscleAttacherService $muscleAttacher,
    ) {
    }

    /**
     * @param Collection<int, ExerciseMuscle> $muscles
     */
    public function edit(Exercise $exercise, Collection $muscles): void
    {
        $this->clearMuscles($exercise);
        $this->muscleAttacher->attach($exercise, $muscles);
        $this->em->flush();
    }

    private function clearMuscles(Exercise $exercise): void
    {
        foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
            $exercise->exerciseMuscles->removeElement($exerciseMuscle);
            $this->em->remove($exerciseMuscle);
        }
    }
}
