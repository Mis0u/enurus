<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Responsabilité unique : rattacher une collection de ExerciseMuscle à un Exercise —
 * logique identique en création et en édition, mutualisée ici.
 */
final readonly class ExerciseMuscleAttacherService
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @param Collection<int, ExerciseMuscle> $muscles
     */
    public function attach(Exercise $exercise, Collection $muscles): void
    {
        foreach ($muscles as $exerciseMuscle) {
            $exerciseMuscle->exercise = $exercise;
            $exercise->exerciseMuscles->add($exerciseMuscle);
            $this->em->persist($exerciseMuscle);
        }
    }
}
