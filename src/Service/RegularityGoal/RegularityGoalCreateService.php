<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

use App\Entity\RegularityGoal;
use App\Repository\RegularityGoalRepository;
use Doctrine\ORM\EntityManagerInterface;

final readonly class RegularityGoalCreateService
{
    public function __construct(
        private RegularityGoalRepository $regularityGoalRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * Un seul objectif en cours (ou à venir) à la fois : refusé tant que le précédent n'est pas
     * terminé ou abandonné.
     *
     * @return bool faux si l'utilisateur a déjà un objectif en cours
     */
    public function create(RegularityGoal $goal): bool
    {
        if ($this->hasOngoingGoal($goal)) {
            return false;
        }

        $this->em->persist($goal);
        $this->em->flush();

        return true;
    }

    private function hasOngoingGoal(RegularityGoal $goal): bool
    {
        $today = new \DateTimeImmutable('today');

        return null !== array_find(
            $this->regularityGoalRepository->findByOwnerNewestFirst($goal->owner),
            static fn (RegularityGoal $existing): bool => $existing->isOngoingOn($today),
        );
    }
}
