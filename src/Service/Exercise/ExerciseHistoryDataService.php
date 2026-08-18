<?php

declare(strict_types=1);

namespace App\Service\Exercise;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseSetRepository;
use App\Service\Utils\WeightConverterService;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\UX\Chartjs\Model\Chart;

/**
 * Données de la page d'historique par exercice (roadmap V1 #24) : tuiles Aperçu (all-time),
 * courbe de progression et journal des séances (filtrés par période) — cf. CLAUDE.md pour les
 * décisions produit actées.
 */
final readonly class ExerciseHistoryDataService
{
    public const string PERIOD_ALL = 'all';

    public const string PERIOD_3_MONTHS = '3m';

    public const string PERIOD_1_YEAR = '1y';

    /**
     * Au-delà de ce nombre de séances dans le sous-ensemble filtré, la courbe bascule sur un
     * point par mois plutôt qu'un point par séance (cf. CLAUDE.md : illisible au-delà).
     */
    private const int MONTHLY_BUCKET_THRESHOLD = 60;

    private const int DAYS_PER_WEEK = 7;

    private const int PERCENT_MULTIPLIER = 100;

    private const int SECONDS_PER_MINUTE = 60;

    public function __construct(
        private ExerciseSetRepository $exerciseSetRepository,
        private WeightConverterService $weightConverter,
        private ExerciseHistoryChartBuilder $chartBuilder,
        private PaginatorInterface $paginator,
    ) {
    }

    /**
     * @return array{
     *     hasHistory: bool,
     *     overview: array{recordValue: float|int, recordDate: ?\DateTimeImmutable, totalSessions: int, totalVolume: float|int, lastPracticedAt: ?\DateTimeImmutable, progressionPercent: ?float, averageFrequencyPerWeek: float},
     *     unitLabel: string,
     *     period: string,
     *     availablePeriods: string[],
     *     chart: Chart,
     *     journal: PaginationInterface<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}>
     * }
     */
    public function getData(User $user, Exercise $exercise, string $period, int $page, int $limit): array
    {
        $measurementType = $exercise->measurementType;
        $rawSets = $this->exerciseSetRepository->findSessionHistoryForExerciseAndUser($user, $exercise);
        $sessions = $this->markRecords($this->aggregateSessions($rawSets, $measurementType));

        $unitLabel = $this->resolveUnitLabel($measurementType, $user);

        if ([] === $sessions) {
            return [
                'hasHistory' => false,
                'overview' => [
                    'recordValue' => 0.0,
                    'recordDate' => null,
                    'totalSessions' => 0,
                    'totalVolume' => 0.0,
                    'lastPracticedAt' => null,
                    'progressionPercent' => null,
                    'averageFrequencyPerWeek' => 0.0,
                ],
                'unitLabel' => $unitLabel,
                'period' => self::PERIOD_ALL,
                'availablePeriods' => [self::PERIOD_ALL],
                'chart' => $this->chartBuilder->build([], $unitLabel),
                'journal' => $this->emptyJournal($page, $limit),
            ];
        }

        $overview = $this->buildOverview($sessions, $measurementType, $user);

        $availablePeriods = $this->resolveAvailablePeriods($sessions);
        $period = in_array($period, $availablePeriods, true) ? $period : self::PERIOD_ALL;

        $filteredSessions = $this->filterByPeriod($sessions, $period);

        $chart = $this->chartBuilder->build(
            $this->buildChartPoints($filteredSessions, $measurementType, $user),
            $unitLabel,
        );

        /** @var PaginationInterface<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $journal */
        $journal = $this->paginator->paginate(
            array_reverse($this->convertSessionsForDisplay($filteredSessions, $measurementType, $user)),
            $page,
            $limit,
        );

        return [
            'hasHistory' => true,
            'overview' => $overview,
            'unitLabel' => $unitLabel,
            'period' => $period,
            'availablePeriods' => $availablePeriods,
            'chart' => $chart,
            'journal' => $journal,
        ];
    }

    /**
     * Agrège les sets bruts par séance : la "série max" de la séance est celle portant la valeur
     * record (poids/durée/distance selon le type de mesure), avec ses propres reps — jamais un
     * `MAX`/`SUM` agrégés indépendamment, qui perdrait l'association poids↔reps exacte si la
     * séance contient plusieurs séries à poids différents (cf. plan). Le volume est la somme de
     * toutes les séries de la séance sur cet exercice.
     *
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, weight: float, reps: int, duration: ?int, distance: ?int}> $rawSets
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float}>
     */
    private function aggregateSessions(array $rawSets, MeasurementType $measurementType): array
    {
        /** @var array<string, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float}> $sessions */
        $sessions = [];

        foreach ($rawSets as $set) {
            $sessions[$set['workoutId']] ??= [
                'workoutId' => $set['workoutId'],
                'performedAt' => $set['performedAt'],
                'recordValue' => 0.0,
                'recordReps' => 0,
                'volume' => 0.0,
            ];

            $value = $this->metricValue($set, $measurementType);

            if ($value > $sessions[$set['workoutId']]['recordValue']) {
                $sessions[$set['workoutId']]['recordValue'] = $value;
                $sessions[$set['workoutId']]['recordReps'] = $set['reps'];
            }

            $sessions[$set['workoutId']]['volume'] += MeasurementType::WEIGHT_REPS === $measurementType
                ? $set['weight'] * $set['reps']
                : $value;
        }

        return array_values($sessions);
    }

    /**
     * @param array{weight: float, reps: int, duration: ?int, distance: ?int} $set
     */
    private function metricValue(array $set, MeasurementType $measurementType): float
    {
        return match ($measurementType) {
            MeasurementType::WEIGHT_REPS => $set['weight'],
            MeasurementType::TIME => (float) ($set['duration'] ?? 0),
            MeasurementType::DISTANCE => (float) ($set['distance'] ?? 0),
        };
    }

    /**
     * Marque chaque séance comme PR si sa valeur record dépasse strictement le max glissant
     * précédent — la première séance compte toujours comme un record (même règle que
     * `WorkoutRecordDetectionService::findPrEvents` pour les PR de poids). Boucle locale et non
     * partagée avec `WorkoutRecordDetectionService` : celui-ci jette la valeur du record, ici on
     * en a besoin pour le graphique et les tuiles.
     *
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float}> $sessions
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}>
     */
    private function markRecords(array $sessions): array
    {
        $runningMax = null;

        return array_map(static function (array $session) use (&$runningMax): array {
            $isPr = null === $runningMax || $session['recordValue'] > $runningMax;

            if ($isPr) {
                $runningMax = $session['recordValue'];
            }

            $session['isPr'] = $isPr;

            return $session;
        }, $sessions);
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $sessions
     * @return array{recordValue: float|int, recordDate: ?\DateTimeImmutable, totalSessions: int, totalVolume: float|int, lastPracticedAt: ?\DateTimeImmutable, progressionPercent: ?float, averageFrequencyPerWeek: float}
     */
    private function buildOverview(array $sessions, MeasurementType $measurementType, User $user): array
    {
        $lastKey = array_key_last($sessions);

        if (null === $lastKey) {
            throw new \LogicException('Expected at least one session.');
        }

        $prSessions = array_values(array_filter($sessions, static fn (array $session): bool => $session['isPr']));
        $currentRecord = end($prSessions);

        if (false === $currentRecord) {
            throw new \LogicException('Expected at least one PR session — markRecords() always flags the first session as a PR.');
        }

        $first = $sessions[0];
        $last = $sessions[$lastKey];

        $daysElapsed = $first['performedAt']->diff($last['performedAt'])->days;
        $weeksElapsed = max(1, (int) ceil($daysElapsed / self::DAYS_PER_WEEK));

        $totalVolume = array_sum(array_column($sessions, 'volume'));
        $recordValue = $currentRecord['recordValue'];
        $firstValue = $first['recordValue'];

        return [
            'recordValue' => $this->overviewValue($recordValue, $measurementType, $user),
            'recordDate' => $currentRecord['performedAt'],
            'totalSessions' => \count($sessions),
            'totalVolume' => $this->overviewValue($totalVolume, $measurementType, $user),
            'lastPracticedAt' => $last['performedAt'],
            'progressionPercent' => 0.0 < $firstValue ? round(($recordValue - $firstValue) / $firstValue * self::PERCENT_MULTIPLIER, 1) : null,
            'averageFrequencyPerWeek' => round(\count($sessions) / $weeksElapsed, 1),
        ];
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $sessions
     * @return string[]
     */
    private function resolveAvailablePeriods(array $sessions): array
    {
        $firstPerformedAt = $sessions[0]['performedAt'];
        $now = new \DateTimeImmutable();

        $periods = [self::PERIOD_ALL];

        if ($firstPerformedAt <= $now->modify('-3 months')) {
            $periods[] = self::PERIOD_3_MONTHS;
        }

        if ($firstPerformedAt <= $now->modify('-1 year')) {
            $periods[] = self::PERIOD_1_YEAR;
        }

        return $periods;
    }

    /**
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $sessions
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}>
     */
    private function filterByPeriod(array $sessions, string $period): array
    {
        if (self::PERIOD_ALL === $period) {
            return $sessions;
        }

        $threshold = (new \DateTimeImmutable())->modify(self::PERIOD_3_MONTHS === $period ? '-3 months' : '-1 year');

        return array_values(array_filter(
            $sessions,
            static fn (array $session): bool => $session['performedAt'] >= $threshold,
        ));
    }

    /**
     * Un point par séance en dessous du seuil (et toujours pour "3 mois"/"1 an"), sinon
     * agrégation mensuelle (valeur max du mois) — même principe de bucketing que
     * `DashboardTonnageService`, sans zero-fill : une courbe de progression n'a pas besoin de
     * points vides sur les mois sans séance.
     *
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $sessions
     * @return array<int, array{label: string, value: float, isPr: bool, isCurrentRecord: bool}>
     */
    private function buildChartPoints(array $sessions, MeasurementType $measurementType, User $user): array
    {
        $lastPrIndex = array_key_last(array_filter($sessions, static fn (array $session): bool => $session['isPr']));

        $formatter = new \IntlDateFormatter($user->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'd MMM');
        $monthFormatter = new \IntlDateFormatter($user->locale, \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'MMM Y');

        if (self::MONTHLY_BUCKET_THRESHOLD >= \count($sessions)) {
            return array_map(
                fn (int $index, array $session): array => [
                    'label' => $this->formatDate($formatter, $session['performedAt']),
                    'value' => $this->displayValue($session['recordValue'], $measurementType, $user),
                    'isPr' => $session['isPr'],
                    'isCurrentRecord' => $index === $lastPrIndex,
                ],
                array_keys($sessions),
                $sessions,
            );
        }

        /** @var array<string, array{performedAt: \DateTimeImmutable, session: array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}}> $bestByMonth */
        $bestByMonth = [];
        foreach ($sessions as $session) {
            $monthKey = $session['performedAt']->format('Y-m');
            if (! isset($bestByMonth[$monthKey]) || $session['recordValue'] > $bestByMonth[$monthKey]['session']['recordValue']) {
                $bestByMonth[$monthKey] = [
                    'performedAt' => $session['performedAt'],
                    'session' => $session,
                ];
            }
        }

        return array_values(array_map(
            fn (array $bucket): array => [
                'label' => $this->formatDate($monthFormatter, $bucket['performedAt']),
                'value' => $this->displayValue($bucket['session']['recordValue'], $measurementType, $user),
                'isPr' => $bucket['session']['isPr'],
                'isCurrentRecord' => $bucket['session']['workoutId'] === ($sessions[$lastPrIndex]['workoutId'] ?? null),
            ],
            $bestByMonth,
        ));
    }

    private function formatDate(\IntlDateFormatter $formatter, \DateTimeImmutable $date): string
    {
        $formatted = $formatter->format($date);

        if (false === $formatted) {
            throw new \LogicException('Failed to format exercise history chart date label.');
        }

        return $formatted;
    }

    /**
     * Convertit `recordValue`/`volume` kg→unité d'affichage pour les exercices `WEIGHT_REPS` —
     * sans objet pour `TIME`/`DISTANCE` (secondes/mètres affichés tels quels par le template via
     * les filtres/traductions déjà existants, `format_set_duration` et l'abréviation "m", même
     * pattern que `workout/show/_exercise_card.html.twig`).
     *
     * @param array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $sessions
     * @return array<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}>
     */
    private function convertSessionsForDisplay(array $sessions, MeasurementType $measurementType, User $user): array
    {
        if (MeasurementType::WEIGHT_REPS !== $measurementType) {
            return $sessions;
        }

        return array_map(function (array $session) use ($user): array {
            $session['recordValue'] = $this->weightConverter->convertToLbs($session['recordValue'], $user->unitOfMeasure);
            $session['volume'] = $this->weightConverter->convertToLbs($session['volume'], $user->unitOfMeasure);

            return $session;
        }, $sessions);
    }

    /**
     * Valeur brute (kg pour `WEIGHT_REPS`, secondes pour `TIME`, mètres pour `DISTANCE`) convertie
     * pour l'affichage du graphique : kg→unité utilisateur, secondes→minutes (cohérent avec
     * l'unité "min" du cahier des charges) ; mètres inchangés. Le journal, lui, reste en secondes
     * brutes pour réutiliser `format_set_duration` (format mm:ss) — granularité différente,
     * volontaire.
     */
    private function displayValue(float $rawValue, MeasurementType $measurementType, User $user): float
    {
        return match ($measurementType) {
            MeasurementType::WEIGHT_REPS => $this->weightConverter->convertToLbs($rawValue, $user->unitOfMeasure),
            MeasurementType::TIME => round($rawValue / self::SECONDS_PER_MINUTE, 1),
            MeasurementType::DISTANCE => $rawValue,
        };
    }

    /**
     * Valeur affichée par les tuiles "Record actuel"/"Volume total" : mêmes conversions que
     * `displayValue()`, sauf pour `TIME` — secondes brutes (entier) plutôt que minutes décimales
     * arrondies (ex. 2min15 affiché "2,3 min" est trompeur, le template le formate en mm:ss via
     * `format_set_duration`, cohérent avec le journal en dessous). Le graphique, lui, garde les
     * minutes décimales de `displayValue()` — un axe en secondes brutes serait illisible.
     */
    private function overviewValue(float $rawValue, MeasurementType $measurementType, User $user): float|int
    {
        return MeasurementType::TIME === $measurementType
            ? (int) round($rawValue)
            : $this->displayValue($rawValue, $measurementType, $user);
    }

    /**
     * @return PaginationInterface<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}>
     */
    private function emptyJournal(int $page, int $limit): PaginationInterface
    {
        /** @var PaginationInterface<int, array{workoutId: string, performedAt: \DateTimeImmutable, recordValue: float, recordReps: int, volume: float, isPr: bool}> $journal */
        $journal = $this->paginator->paginate([], $page, $limit);

        return $journal;
    }

    private function resolveUnitLabel(MeasurementType $measurementType, User $user): string
    {
        return match ($measurementType) {
            MeasurementType::WEIGHT_REPS => $user->unitOfMeasure->value,
            MeasurementType::TIME => 'min',
            MeasurementType::DISTANCE => 'm',
        };
    }
}
