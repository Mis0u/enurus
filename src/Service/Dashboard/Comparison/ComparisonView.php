<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Comparison;

use App\Enum\Dashboard\ComparisonGranularityEnum;

/**
 * Un onglet du widget Comparaison (semaine, mois ou année), tout prêt pour la vue.
 */
final readonly class ComparisonView
{
    /**
     * @param list<MetricComparison> $metrics
     * @param string $unit            unité du tonnage (celle de qui regarde) : kg ou lbs
     * @param bool $hasPreviousData  faux si aucune séance sur la période précédente : pas de chiffres
     *                               à comparer (évite des « +100 % » absurdes)
     */
    public function __construct(
        public ComparisonGranularityEnum $granularity,
        public ComparisonPeriods $periods,
        public array $metrics,
        public string $unit,
        public bool $hasPreviousData,
        public bool $currentIncludesDeload,
        public bool $previousIncludesDeload,
    ) {
    }
}
