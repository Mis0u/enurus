<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Routine;
use App\Repository\RoutineRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Une routine archivée libère son nom : une routine active a pu le reprendre entre-temps. Dans ce
 * cas la routine reste archivée, jamais renommée d'office puisque ce nom figure sur ses séances.
 */
final readonly class RoutineRestoreService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoutineRepository $routineRepository,
    ) {
    }

    /**
     * @return bool false si une routine active porte déjà ce nom
     */
    public function restore(Routine $routine): bool
    {
        if ($this->routineRepository->existsByNameForUser($routine->name, $routine->owner, $routine->id)) {
            return false;
        }

        $routine->archived = false;
        $this->em->flush();

        return true;
    }
}
