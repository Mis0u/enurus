<?php

declare(strict_types=1);

namespace App\Service\Dashboard;

/**
 * Bornes de périodes (semaine/mois/année) partagées par tous les widgets du dashboard —
 * source de vérité unique évitant que chaque service recalcule sa propre variante des mêmes
 * formules de date. Une période "courante" s'arrête à $now (progression en cours), une période
 * "précédente" couvre l'intégralité de la période passée.
 */
final readonly class DashboardPeriodCalculator
{
    public function dayOf(\DateTimeImmutable $date): DashboardPeriod
    {
        return new DashboardPeriod(
            $date->setTime(0, 0, 0),
            $date->setTime(23, 59, 59),
        );
    }

    public function currentWeek(\DateTimeImmutable $now): DashboardPeriod
    {
        $start = $this->weekStartOf($now);

        return new DashboardPeriod($start, $start->modify('+6 days')->setTime(23, 59, 59));
    }

    public function previousWeek(\DateTimeImmutable $now): DashboardPeriod
    {
        $current = $this->currentWeek($now);

        return new DashboardPeriod(
            $current->start->modify('-7 days'),
            $current->end->modify('-7 days'),
        );
    }

    public function currentMonthElapsed(\DateTimeImmutable $now): DashboardPeriod
    {
        return new DashboardPeriod(
            $now->modify('first day of this month')->setTime(0, 0, 0),
            $now->setTime(23, 59, 59),
        );
    }

    public function previousMonth(\DateTimeImmutable $now): DashboardPeriod
    {
        return new DashboardPeriod(
            $now->modify('first day of last month')->setTime(0, 0, 0),
            $now->modify('last day of last month')->setTime(23, 59, 59),
        );
    }

    public function currentYearElapsed(\DateTimeImmutable $now): DashboardPeriod
    {
        return new DashboardPeriod(
            $now->modify('first day of january this year')->setTime(0, 0, 0),
            $now->setTime(23, 59, 59),
        );
    }

    public function previousYear(\DateTimeImmutable $now): DashboardPeriod
    {
        return new DashboardPeriod(
            $now->modify('first day of january last year')->setTime(0, 0, 0),
            $now->modify('last day of december last year')->setTime(23, 59, 59),
        );
    }

    public function weekStartOf(\DateTimeImmutable $date): \DateTimeImmutable
    {
        $dayOfWeek = (int) $date->format('N');

        return $date->modify(sprintf('-%d days', $dayOfWeek - 1))->setTime(0, 0, 0);
    }
}
