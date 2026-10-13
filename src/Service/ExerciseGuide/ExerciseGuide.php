<?php

declare(strict_types=1);

namespace App\Service\ExerciseGuide;

/**
 * Structure de la fiche publique d'un exercice du catalogue. Les textes sont dans le domaine de
 * traduction `exercise_guide`, sous la clé de l'exercice (`bench_press_bar.steps.1`…).
 */
final readonly class ExerciseGuide
{
    /**
     * @param string       $key          clé de l'exercice public (`bench_press_bar`), préfixe de son nom en base
     * @param list<string> $variantKeys  clés d'autres exercices publics présentés comme variantes
     */
    public function __construct(
        public string $key,
        public int $stepCount,
        public int $mistakeCount,
        public array $variantKeys,
    ) {
    }

    /**
     * Nom de l'exercice en base (`Exercise::$name`), qui est aussi sa clé de traduction.
     */
    public function exerciseName(): string
    {
        return $this->key . '.name';
    }

    /**
     * @return list<string>
     */
    public function stepKeys(): array
    {
        return array_map(fn (int $number): string => $this->key . '.steps.' . $number, range(1, $this->stepCount));
    }

    /**
     * @return list<array{title: string, text: string}>
     */
    public function mistakeKeys(): array
    {
        return array_map(fn (int $number): array => [
            'title' => $this->key . '.mistakes.' . $number . '.title',
            'text' => $this->key . '.mistakes.' . $number . '.text',
        ], range(1, $this->mistakeCount));
    }

    public function introKey(): string
    {
        return $this->key . '.intro';
    }

    public function variantReasonKey(string $variantKey): string
    {
        return $this->key . '.variants.' . $variantKey;
    }
}
