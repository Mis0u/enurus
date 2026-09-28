<?php

declare(strict_types=1);

namespace App\Service\Dashboard\Comparison;

/**
 * Une ligne du widget Comparaison, déjà calculée : valeur de la période en cours, de la période
 * précédente, écart et variation en %. Le sens est neutre (hausse/baisse), jamais un jugement :
 * moins de répétitions peut venir de charges plus lourdes.
 */
final readonly class MetricComparison
{
    private const int PERCENT = 100;

    public float $delta;

    // Nul quand la période précédente vaut 0 : un pourcentage n'y aurait pas de sens.
    public ?int $percent;

    public string $trend;

    public function __construct(
        public string $key,
        public float $current,
        public float $previous,
    ) {
        $this->delta = $current - $previous;
        $this->percent = 0.0 < $previous ? (int) round($this->delta / $previous * self::PERCENT) : null;
        $this->trend = match (true) {
            0.0 < $this->delta => 'up',
            0.0 > $this->delta => 'down',
            default => 'equal',
        };
    }
}
