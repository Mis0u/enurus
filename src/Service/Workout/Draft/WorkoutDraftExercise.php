<?php

declare(strict_types=1);

namespace App\Service\Workout\Draft;

/**
 * Un exercice du brouillon de séance tenu par le navigateur (`workout--draft` controller), tel
 * que saisi : valeurs dans l'unité d'affichage de l'utilisateur, champs vides à `null`.
 *
 * @phpstan-type DraftSet array{weight: ?float, reps: ?int, duration: ?int, distance: ?int}
 */
final readonly class WorkoutDraftExercise
{
    /**
     * @param list<DraftSet> $sets vide pour un exercice ajouté sans saisie (ex. tout juste créé)
     * @param bool $prefilled la carte montrait encore « Repris de ta séance du… », jamais effacée
     */
    public function __construct(
        public string $exerciseId,
        public array $sets,
        public bool $prefilled = false,
    ) {
    }
}
