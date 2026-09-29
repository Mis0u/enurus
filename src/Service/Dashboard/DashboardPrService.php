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
     * Un exercice jamais fait avant compte automatiquement comme un premier record. Le flux est fourni
     * par l'appelant, qui le partage avec le widget Comparaison pour ne le calculer qu'une fois.
     *
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $prEvents cf. `WorkoutRecordDetectionService::findPrEvents()`
     * @return array{last: int, week: int, month: int}
     */
    public function countPrsByFilter(array $prEvents, DashboardPeriods $periods): array
    {
        return $this->countEventsByFilter($prEvents, $periods);
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
    public function countRepsRecordsByFilter(User $user, DashboardPeriods $periods): array
    {
        $events = $this->workoutRecordDetectionService->findRepsRecordEvents($user);

        return $this->countEventsByFilter($events, $periods);
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $events
     * @return array{last: int, week: int, month: int}
     */
    private function countEventsByFilter(array $events, DashboardPeriods $periods): array
    {
        return [
            'last' => self::countEventsIn($events, $periods->day),
            'week' => self::countEventsIn($events, $periods->week),
            'month' => self::countEventsIn($events, $periods->month),
        ];
    }

    /**
     * Bornes incluses des deux côtés.
     *
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable}> $events
     */
    private static function countEventsIn(array $events, DashboardPeriod $period): int
    {
        return \count(array_filter(
            $events,
            static fn (array $event): bool => $event['performedAt'] >= $period->start && $event['performedAt'] <= $period->end,
        ));
    }
}
