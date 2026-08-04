<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

use App\Entity\User;
use App\Service\Workout\WorkoutRecordDetectionService;

final readonly class DashboardPrService
{
    public function __construct(
        private WorkoutRecordDetectionService $workoutRecordDetectionService,
    ) {
    }

    /**
     * Nombre de nouveaux records personnels (PR) de poids battus par filtre du widget Séance
     * (Dernière journée/Semaine/Mois courant) — même définition de PR que WorkoutShowController
     * (poids max sur un exercice, jamais poids × reps, un seul PR possible par séance et par
     * exercice), détectée progressivement sur tout l'historique chronologique (2 séances distinctes
     * battant chacune le record sur le même exercice pendant la période comptent pour 2, pas 1).
     * Un exercice jamais fait avant compte automatiquement comme un premier record.
     *
     * @return array{last: int, week: int, month: int}
     */
    public function countPrsByFilter(
        User $user,
        DashboardPeriod $day,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $events = $this->workoutRecordDetectionService->findPrEvents($user);

        return $this->countEventsByFilter($events, $day, $week, $month);
    }

    /**
     * Nombre de nouveaux records de répétitions (même poids qu'avant, mais jamais fait à autant de
     * reps) battus par filtre — même mécanique de détection progressive que `countPrsByFilter`,
     * mais la clé de comparaison est (exercice, poids exact) au lieu de (exercice) seul, et un
     * poids jamais fait avant ne compte volontairement pas comme un record de reps automatique
     * (ce cas est déjà couvert par le PR de poids : pas de double comptage).
     *
     * @return array{last: int, week: int, month: int}
     */
    public function countRepsRecordsByFilter(
        User $user,
        DashboardPeriod $day,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $events = $this->workoutRecordDetectionService->findRepsRecordEvents($user);

        return $this->countEventsByFilter($events, $day, $week, $month);
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $events
     * @return array{last: int, week: int, month: int}
     */
    private function countEventsByFilter(
        array $events,
        DashboardPeriod $day,
        DashboardPeriod $week,
        DashboardPeriod $month,
    ): array {
        $dayCount = 0;
        $weekCount = 0;
        $monthCount = 0;

        foreach ($events as $event) {
            if ($event['performedAt'] >= $day->start && $event['performedAt'] <= $day->end) {
                $dayCount++;
            }

            if ($event['performedAt'] >= $week->start && $event['performedAt'] <= $week->end) {
                $weekCount++;
            }

            if ($event['performedAt'] >= $month->start && $event['performedAt'] <= $month->end) {
                $monthCount++;
            }
        }

        return [
            'last' => $dayCount,
            'week' => $weekCount,
            'month' => $monthCount,
        ];
    }
}
