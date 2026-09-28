<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

use App\Entity\RegularityGoal;
use App\Entity\User;
use App\Repository\RegularityGoalRepository;
use App\Repository\WorkoutStatsRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Après l'enregistrement ou la modification d'une séance : les objectifs de régularité qu'elle vient
 * de compléter, à célébrer. Chacun est marqué `achievedAt` pour ne l'être qu'une seule fois.
 */
final readonly class RegularityGoalAchievementDetector
{
    public function __construct(
        private RegularityGoalRepository $regularityGoalRepository,
        private WorkoutStatsRepository $workoutStatsRepository,
        private RegularityGoalProgressCalculator $progressCalculator,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return list<RegularityGoal>
     */
    public function detectNewlyAchieved(User $user): array
    {
        $notCelebratedYet = array_filter(
            $this->regularityGoalRepository->findByOwnerNewestFirst($user),
            static fn (RegularityGoal $goal): bool => null === $goal->achievedAt,
        );

        if ([] === $notCelebratedYet) {
            return [];
        }

        $newlyAchieved = $this->achievedAmong($notCelebratedYet, $user);

        if ([] !== $newlyAchieved) {
            $this->em->flush();
        }

        return $newlyAchieved;
    }

    /**
     * @param array<RegularityGoal> $goals
     * @return list<RegularityGoal>
     */
    private function achievedAmong(array $goals, User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $workoutDates = $this->workoutStatsRepository->findAllPerformedDatesByUser($user);
        $achieved = [];

        foreach ($goals as $goal) {
            if ($this->progressCalculator->calculate($goal, $workoutDates, $today)->isAchieved) {
                $goal->achievedAt = new \DateTimeImmutable();
                $achieved[] = $goal;
            }
        }

        return $achieved;
    }
}
