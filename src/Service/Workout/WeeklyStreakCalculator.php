<?php

declare(strict_types=1);

namespace App\Service\Workout;

/**
 * Séries de semaines consécutives avec au moins une séance — seule règle, partagée par le widget
 * Régularité (série en cours + record) et le badge Régularité, pour que les deux ne divergent
 * jamais.
 *
 * Une semaine de deload **relie** la série sans l'allonger : elle ne la casse pas, mais ne compte
 * pas non plus (sinon une longue période de deload suffirait à construire une série sans aucune
 * séance). Une semaine vide, ni séance ni deload, remet la série à zéro. Les semaines futures
 * (séance ou deload planifiés) sont ignorées.
 */
final readonly class WeeklyStreakCalculator
{
    private const string WEEK_KEY_FORMAT = 'Y-m-d';

    /**
     * @param \DateTimeImmutable[] $workoutDates
     * @param array<string, true>  $deloadWeekSet clé = lundi `Y-m-d`, cf. `DeloadPeriodSetService::weekKeySet()`
     */
    public function longestStreak(array $workoutDates, array $deloadWeekSet, \DateTimeImmutable $now): int
    {
        $best = 0;

        foreach ($this->streakLengthByWeek($workoutDates, $deloadWeekSet, $now) as $length) {
            $best = max($best, $length);
        }

        return $best;
    }

    /**
     * Lundi de la première semaine où la série atteint `$length` — sert à retrouver la séance qui a
     * réellement débloqué un badge Régularité obtenu rétroactivement.
     *
     * @param \DateTimeImmutable[] $workoutDates
     * @param array<string, true>  $deloadWeekSet
     */
    public function weekReaching(array $workoutDates, array $deloadWeekSet, int $length, \DateTimeImmutable $now): ?\DateTimeImmutable
    {
        foreach ($this->streakLengthByWeek($workoutDates, $deloadWeekSet, $now) as $monday => $streakLength) {
            if ($streakLength >= $length) {
                return new \DateTimeImmutable($monday);
            }
        }

        return null;
    }

    /**
     * Série qui se termine cette semaine — ou la semaine dernière si celle-ci n'a encore aucune
     * séance (la semaine en cours n'est pas finie, elle ne casse rien).
     *
     * @param \DateTimeImmutable[] $workoutDates
     * @param array<string, true>  $deloadWeekSet
     */
    public function currentStreak(array $workoutDates, array $deloadWeekSet, \DateTimeImmutable $now): int
    {
        $currentMonday = self::mondayOf($now);
        $workoutWeekSet = $this->workoutWeekSet($workoutDates, $currentMonday);
        $week = isset($workoutWeekSet[$currentMonday->format(self::WEEK_KEY_FORMAT)])
            ? $currentMonday
            : $currentMonday->modify('-7 days');

        $streak = 0;

        while (true) {
            $key = $week->format(self::WEEK_KEY_FORMAT);

            if (isset($workoutWeekSet[$key])) {
                $streak++;
            } elseif (! isset($deloadWeekSet[$key])) {
                return $streak;
            }

            $week = $week->modify('-7 days');
        }
    }

    /**
     * Longueur de la série à la fin de chaque semaine, de la première semaine avec séance jusqu'à
     * la semaine en cours, dans l'ordre chronologique.
     *
     * @param \DateTimeImmutable[] $workoutDates
     * @param array<string, true>  $deloadWeekSet
     * @return \Generator<string, int> clé = lundi `Y-m-d`
     */
    private function streakLengthByWeek(array $workoutDates, array $deloadWeekSet, \DateTimeImmutable $now): \Generator
    {
        $currentMonday = self::mondayOf($now);
        $workoutWeekSet = $this->workoutWeekSet($workoutDates, $currentMonday);

        if ([] === $workoutWeekSet) {
            return;
        }

        $current = 0;
        $week = new \DateTimeImmutable(min(array_keys($workoutWeekSet)));

        while ($week <= $currentMonday) {
            $key = $week->format(self::WEEK_KEY_FORMAT);
            $current = $this->nextStreakLength($current, isset($workoutWeekSet[$key]), isset($deloadWeekSet[$key]));

            yield $key => $current;

            $week = $week->modify('+7 days');
        }
    }

    /**
     * @param \DateTimeImmutable[] $workoutDates
     * @return array<string, true>
     */
    private function workoutWeekSet(array $workoutDates, \DateTimeImmutable $currentMonday): array
    {
        $weeks = [];

        foreach ($workoutDates as $date) {
            $monday = self::mondayOf($date);

            if ($monday <= $currentMonday) {
                $weeks[$monday->format(self::WEEK_KEY_FORMAT)] = true;
            }
        }

        return $weeks;
    }

    private function nextStreakLength(int $current, bool $hasWorkout, bool $isDeload): int
    {
        return match (true) {
            $hasWorkout => $current + 1,
            $isDeload => $current,
            default => 0,
        };
    }

    private static function mondayOf(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->modify(\sprintf('-%d days', ((int) $date->format('N')) - 1))->setTime(0, 0);
    }
}
