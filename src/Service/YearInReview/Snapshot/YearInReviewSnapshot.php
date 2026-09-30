<?php

declare(strict_types=1);

namespace App\Service\YearInReview\Snapshot;

/**
 * Chiffres figés d'un résumé annuel, calculés une seule fois à sa publication
 * (`YearInReviewBuilder`) puis stockés en JSON dans `YearInReview`. Valeurs brutes uniquement
 * (kg, ids, clés d'enum) : langue et unité du lecteur s'appliquent à l'affichage.
 *
 * Une section vide (liste vide, `null`, ou zéro) masque son écran.
 */
final readonly class YearInReviewSnapshot
{
    /**
     * @param array<string, float>       $tonnageKgByDay tonnage par jour (`Y-m-d`), pour la heatmap
     * @param list<YearInReviewExercise> $topExercises   3 au plus, le plus fréquent en premier
     * @param list<YearInReviewBadge>    $badges         débloqués pendant la période
     */
    public function __construct(
        public YearInReviewTotals $totals,
        public array $tonnageKgByDay,
        public array $topExercises,
        public ?YearInReviewMuscle $topMuscle,
        public YearInReviewRecords $records,
        public YearInReviewRegularity $regularity,
        public array $badges,
        public ?YearInReviewMood $dominantMood,
    ) {
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $topMuscle = SnapshotArrayReader::nullableArray($data, 'topMuscle');
        $dominantMood = SnapshotArrayReader::nullableArray($data, 'dominantMood');

        return new self(
            YearInReviewTotals::fromArray(SnapshotArrayReader::array($data, 'totals')),
            self::tonnageKgByDay(SnapshotArrayReader::array($data, 'tonnageKgByDay')),
            array_map(YearInReviewExercise::fromArray(...), SnapshotArrayReader::listOfArrays(SnapshotArrayReader::array($data, 'topExercises'))),
            null === $topMuscle ? null : YearInReviewMuscle::fromArray($topMuscle),
            YearInReviewRecords::fromArray(SnapshotArrayReader::array($data, 'records')),
            YearInReviewRegularity::fromArray(SnapshotArrayReader::array($data, 'regularity')),
            array_map(YearInReviewBadge::fromArray(...), SnapshotArrayReader::listOfArrays(SnapshotArrayReader::array($data, 'badges'))),
            null === $dominantMood ? null : YearInReviewMood::fromArray($dominantMood),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'totals' => $this->totals->toArray(),
            'tonnageKgByDay' => $this->tonnageKgByDay,
            'topExercises' => array_map(static fn (YearInReviewExercise $exercise): array => $exercise->toArray(), $this->topExercises),
            'topMuscle' => $this->topMuscle?->toArray(),
            'records' => $this->records->toArray(),
            'regularity' => $this->regularity->toArray(),
            'badges' => array_map(static fn (YearInReviewBadge $badge): array => $badge->toArray(), $this->badges),
            'dominantMood' => $this->dominantMood?->toArray(),
        ];
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<string, float>
     */
    private static function tonnageKgByDay(array $data): array
    {
        $tonnageKgByDay = [];

        foreach (array_keys($data) as $day) {
            $tonnageKgByDay[(string) $day] = SnapshotArrayReader::float($data, (string) $day);
        }

        return $tonnageKgByDay;
    }
}
