<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\DeloadPeriod;
use App\Entity\User;
use App\Repository\DeloadPeriodRepository;

/**
 * Expanse les périodes de deload d'un utilisateur en ensembles de clés jour/semaine — partagé par
 * le widget et le badge Régularité (via `WeeklyStreakCalculator` : une semaine deload relie la
 * série sans l'allonger) et le service heatmap (badge deload par jour). Pure transformation en
 * mémoire depuis les entités déjà chargées, aucune requête supplémentaire par appel.
 */
readonly class DeloadPeriodSetService
{
    private const int DAYS_PER_WEEK = 7;

    private const int MAX_PERIOD_WEEKS = 366;

    public function __construct(
        private DeloadPeriodRepository $deloadPeriodRepository,
    ) {
    }

    /**
     * @return array<string, true> clé `Y-m-d` => true pour chaque jour couvert par un deload
     */
    public function dayKeySet(User $user): array
    {
        $days = [];

        foreach ($this->deloadPeriodRepository->findByOwnerOrderedByStartDate($user) as $period) {
            foreach ($this->expandDays($period) as $day) {
                $days[$day->format('Y-m-d')] = true;
            }
        }

        return $days;
    }

    /**
     * @return array<string, true> clé lundi `Y-m-d` de la semaine => true, pour chaque semaine
     *         touchée, même partiellement, par un deload — même convention que
     *         `WeeklyStreakCalculator` (un deload relie la série sans l'allonger).
     */
    public function weekKeySet(User $user): array
    {
        $weeks = [];

        foreach ($this->deloadPeriodRepository->findByOwnerOrderedByStartDate($user) as $period) {
            foreach ($this->expandDays($period) as $day) {
                $monday = $day->modify(sprintf('-%d days', ((int) $day->format('N')) - 1));
                $weeks[$monday->format('Y-m-d')] = true;
            }
        }

        return $weeks;
    }

    /**
     * @return iterable<\DateTimeImmutable>
     */
    private function expandDays(DeloadPeriod $period): iterable
    {
        $day = $period->startDate->setTime(0, 0, 0);
        $end = $period->endDate->setTime(0, 0, 0);

        // Borne défensive : une période mal saisie ne doit jamais boucler indéfiniment.
        $maxIterations = self::DAYS_PER_WEEK * self::MAX_PERIOD_WEEKS;

        for ($i = 0; $day <= $end && $i < $maxIterations; $i++) {
            yield $day;
            $day = $day->modify('+1 day');
        }
    }
}
