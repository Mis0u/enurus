<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

final readonly class ExerciseEditService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Collection<int, ExerciseMuscle> $muscles
     */
    public function edit(Exercise $exercise, Collection $muscles): void
    {
        $this->clearMuscles($exercise);
        $this->attachMuscles($exercise, $muscles);
        $this->em->flush();
    }

    /**
     * @param Collection<int, ExerciseMuscle> $muscles
     */
    public function hasPrimaryMuscle(Collection $muscles): bool
    {
        foreach ($muscles as $exerciseMuscle) {
            if (MuscleTypeEnum::PRIMARY === $exerciseMuscle->type) {
                return true;
            }
        }

        return false;
    }

    private function clearMuscles(Exercise $exercise): void
    {
        foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
            $exercise->exerciseMuscles->removeElement($exerciseMuscle);
            $this->em->remove($exerciseMuscle);
        }
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
