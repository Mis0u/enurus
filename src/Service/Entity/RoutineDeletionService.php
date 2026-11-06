<?php

declare(strict_types=1);

namespace App\Service\Entity;

use App\Entity\Routine;
use App\Repository\RoutineStatsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Supprimer une routine utilisée transformerait toutes ses séances en séances libres : l'historique
 * ne doit jamais être modifié rétroactivement. Elle est donc archivée, comme un exercice déjà logué
 * (cf. ExerciseDeletionService). Une routine sans séance, simple gabarit, est réellement supprimée.
 */
final readonly class RoutineDeletionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RoutineStatsRepository $routineStatsRepository,
    ) {
    }

    /**
     * @return bool true si la routine a été archivée plutôt que réellement supprimée
     */
    public function delete(Routine $routine): bool
    {
        if ($this->routineStatsRepository->isUsed($routine)) {
            $routine->archived = true;
            $this->em->flush();

            return true;
        }

        $this->em->remove($routine);
        $this->em->flush();

        return false;
    }
}
