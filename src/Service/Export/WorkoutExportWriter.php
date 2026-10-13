<?php

declare(strict_types=1);

namespace App\Service\Export;

use App\Entity\User;
use App\Entity\Workout;
use App\Enum\Export\WorkoutExportFormatEnum;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Écrit l'historique de séances dans un format d'export. Les séances arrivent une par une (lecture
 * par lots) : un writer écrit au fil de l'eau, sans jamais garder tout l'historique en mémoire.
 */
#[AutoconfigureTag]
interface WorkoutExportWriter
{
    public function format(): WorkoutExportFormatEnum;

    /**
     * @param iterable<Workout> $workouts
     * @param resource          $stream
     */
    public function write(User $user, iterable $workouts, $stream, string $locale): void;
}
