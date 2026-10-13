<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide;

/**
 * Exercices du catalogue qui ont une fiche publique. Un exercice absent d'ici n'a pas de page : on
 * publie les fiches au fur et à mesure qu'elles sont rédigées, jamais de page vide.
 */
final readonly class ExerciseGuideCatalog
{
    /**
     * Clé de l'exercice => [nombre d'étapes, nombre d'erreurs fréquentes, variantes].
     *
     * @var array<string, array{int, int, list<string>}>
     */
    private const array GUIDES = [
        'bench_press_bar' => [5, 4, ['bench_press_dumbbell', 'incline_bench_press_dumbbell', 'decline_bench_press_bar']],
        'incline_bench_press_dumbbell' => [5, 3, ['incline_bench_press_bar', 'bench_press_bar', 'dumbbell_fly_incline']],
        'dips_chest' => [4, 3, ['dips_triceps', 'push_up', 'bench_press_bar']],
        'pull_up' => [5, 3, ['chin_up', 'lat_pulldown']],
        'barbell_row' => [5, 3, ['dumbbell_row', 'cable_row']],
        'deadlift' => [5, 4, ['romanian_deadlift', 'stiff_leg_deadlift']],
        'military_press_bar' => [5, 3, ['arnold_press', 'military_press_machine']],
        'barbell_curl_standing' => [4, 3, ['ez_bar_curl', 'dumbbell_curl_standing', 'preacher_curl']],
        'barbell_squat' => [5, 4, ['barbell_front_squat', 'hack_squat', 'leg_press']],
        'leg_press' => [5, 3, ['barbell_squat', 'hack_squat']],
    ];

    /**
     * @return list<ExerciseGuide>
     */
    public function all(): array
    {
        return array_map($this->build(...), array_keys(self::GUIDES));
    }

    public function find(string $key): ?ExerciseGuide
    {
        return \array_key_exists($key, self::GUIDES) ? $this->build($key) : null;
    }

    private function build(string $key): ExerciseGuide
    {
        [$stepCount, $mistakeCount, $variantKeys] = self::GUIDES[$key];

        return new ExerciseGuide($key, $stepCount, $mistakeCount, $variantKeys);
    }
}
