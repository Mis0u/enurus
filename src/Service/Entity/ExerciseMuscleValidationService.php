<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\ExerciseMuscle;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use Doctrine\Common\Collections\Collection;

/**
 * Responsabilité unique : valider qu'une collection de ExerciseMuscle contient au moins un
 * muscle primaire — règle commune à la création et à l'édition d'un Exercise.
 */
final class ExerciseMuscleValidationService
{
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
}
