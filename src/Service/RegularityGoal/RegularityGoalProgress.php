<?php

declare(strict_types=1);

namespace App\Service\RegularityGoal;

use App\Entity\RegularityGoal;

/**
 * Progression d'un objectif de régularité, recalculée depuis les séances à chaque affichage.
 */
final readonly class RegularityGoalProgress
{
    /**
     * @param list<RegularityGoalPeriodProgress> $periods
     * @param int  $requiredCount nombre de périodes du défi, toutes exigées
     * @param bool $isAchieved    chaque période a atteint son quota — vrai dès que la dernière
     *                            l'atteint, sans attendre la fin du défi
     */
    public function __construct(
        public RegularityGoal $goal,
        public array $periods,
        public int $metCount,
        public int $requiredCount,
        public bool $isFinished,
        public bool $isAchieved,
    ) {
    }
}
