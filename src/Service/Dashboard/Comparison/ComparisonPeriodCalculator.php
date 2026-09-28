<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Comparison;

use App\Enum\Dashboard\ComparisonGranularityEnum;
use App\Service\Dashboard\DashboardPeriod;

/**
 * « À période égale » : du début de la semaine / du mois / de l'année jusqu'à aujourd'hui, contre
 * les mêmes jours de la période précédente. Quand ce jour n'existe pas dans la période précédente,
 * on s'arrête à sa fin (le 31 oct. se compare au 1er → 30 sept., un 29 février au 28) : convention
 * des outils d'analyse, les dates exactes étant toujours affichées dans le widget.
 */
final readonly class ComparisonPeriodCalculator
{
    private const int DAYS_PER_WEEK = 7;

    public function calculate(ComparisonGranularityEnum $granularity, \DateTimeImmutable $now): ComparisonPeriods
    {
        $today = $now->setTime(0, 0, 0);
        $currentStart = $this->currentStart($granularity, $today);
        $previousStart = $this->previousStart($granularity, $currentStart);

        return new ComparisonPeriods(
            new DashboardPeriod($currentStart, self::endOfDay($today)),
            new DashboardPeriod($previousStart, self::endOfDay($this->sameDayIn($granularity, $previousStart, $today))),
        );
    }

    private function currentStart(ComparisonGranularityEnum $granularity, \DateTimeImmutable $today): \DateTimeImmutable
    {
        return match ($granularity) {
            ComparisonGranularityEnum::WEEK => $today->modify(\sprintf('-%d days', (int) $today->format('N') - 1)),
            ComparisonGranularityEnum::MONTH => $today->modify('first day of this month'),
            ComparisonGranularityEnum::YEAR => $today->setDate((int) $today->format('Y'), 1, 1),
        };
    }

    private function previousStart(ComparisonGranularityEnum $granularity, \DateTimeImmutable $currentStart): \DateTimeImmutable
    {
        return match ($granularity) {
            ComparisonGranularityEnum::WEEK => $currentStart->modify(\sprintf('-%d days', self::DAYS_PER_WEEK)),
            ComparisonGranularityEnum::MONTH => $currentStart->modify('first day of previous month'),
            ComparisonGranularityEnum::YEAR => $currentStart->modify('-1 year'),
        };
    }

    /**
     * Jour de la période précédente qui correspond à aujourd'hui, borné à la fin de son mois.
     */
    private function sameDayIn(ComparisonGranularityEnum $granularity, \DateTimeImmutable $previousStart, \DateTimeImmutable $today): \DateTimeImmutable
    {
        if (ComparisonGranularityEnum::WEEK === $granularity) {
            return $today->modify(\sprintf('-%d days', self::DAYS_PER_WEEK));
        }

        $monthStart = ComparisonGranularityEnum::MONTH === $granularity
            ? $previousStart
            : $previousStart->setDate((int) $previousStart->format('Y'), (int) $today->format('n'), 1);
        $day = min((int) $today->format('j'), (int) $monthStart->format('t'));

        return $monthStart->modify(\sprintf('+%d days', $day - 1));
    }

    private static function endOfDay(\DateTimeImmutable $day): \DateTimeImmutable
    {
        return $day->setTime(23, 59, 59);
    }
}
