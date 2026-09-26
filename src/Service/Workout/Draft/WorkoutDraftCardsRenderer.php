<?php

declare(strict_types=1);

namespace App\Service\Workout\Draft;

use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Service\Workout\WorkoutExerciseCardDataBuilder;
use App\Service\Workout\WorkoutExerciseCardRenderer;

/**
 * Recrée les cartes d'exercice d'un brouillon de séance, dans l'ordre du brouillon, avec les
 * valeurs saisies — et la mention « Repris de ta séance du… » tant que l'utilisateur ne l'a pas
 * effacée. Un exercice sans série saisie (ex. tout juste créé depuis la séance) reçoit le
 * même pré-remplissage qu'un ajout via le sélecteur.
 *
 * @phpstan-import-type CardData from WorkoutExerciseCardDataBuilder
 */
final class WorkoutDraftCardsRenderer
{
    private const string CONTROLLER_NAME = 'exercise';

    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly WorkoutExerciseCardDataBuilder $cardDataBuilder,
        private readonly WorkoutExerciseCardRenderer $cardRenderer,
    ) {
    }

    /**
     * @param list<WorkoutDraftExercise> $draftExercises
     * @return list<string> HTML des cartes, index en placeholder (cf. WorkoutExerciseCardRenderer)
     */
    public function render(User $user, array $draftExercises): array
    {
        $restorable = $this->restorableExercises($user, $draftExercises);
        $cardData = $this->cardDataBuilder->build($user, array_column($restorable, 'exercise'));

        return array_map(
            fn (array $entry): string => $this->cardRenderer->render(
                $entry['exercise'],
                $this->withDraftSets($user, $entry, $cardData[(string) $entry['exercise']->id]),
                self::CONTROLLER_NAME,
            ),
            $restorable,
        );
    }

    /**
     * Les identifiants viennent du navigateur : seuls les exercices réellement proposés à
     * l'utilisateur (publics ou à lui, non archivés) sont retenus, jamais l'exercice privé d'un
     * autre. Un exercice supprimé ou archivé depuis la saisie disparaît simplement du brouillon.
     *
     * @param list<WorkoutDraftExercise> $draftExercises
     * @return list<array{exercise: Exercise, draft: WorkoutDraftExercise}>
     */
    private function restorableExercises(User $user, array $draftExercises): array
    {
        $availableById = [];
        foreach ($this->exerciseRepository->findAvailableForUser($user) as $exercise) {
            $availableById[(string) $exercise->id] = $exercise;
        }

        $restorable = [];
        foreach ($draftExercises as $draftExercise) {
            $exercise = $availableById[$draftExercise->exerciseId] ?? null;

            if (null !== $exercise) {
                $restorable[] = [
                    'exercise' => $exercise,
                    'draft' => $draftExercise,
                ];
            }
        }

        return $restorable;
    }

    /**
     * Un exercice au poids de corps sans poids renseigné garde une carte vide : c'est ce qui la
     * laisse bloquée (`_exercise_card.html.twig` ne bloque jamais une carte qui contient des séries).
     *
     * @param array{exercise: Exercise, draft: WorkoutDraftExercise} $entry
     * @param CardData $cardData
     * @return array{cardBodyweightShare: ?float, existingSets: list<array<string, mixed>>, prefilledFrom: ?\DateTimeImmutable}
     */
    private function withDraftSets(User $user, array $entry, array $cardData): array
    {
        $isBlocked = null !== $entry['exercise']->bodyweightPercent && null === $user->bodyweightKg;

        if ([] === $entry['draft']->sets || $isBlocked) {
            return $cardData;
        }

        return [
            'cardBodyweightShare' => $cardData['cardBodyweightShare'],
            'existingSets' => $entry['draft']->sets,
            'prefilledFrom' => $entry['draft']->prefilled ? $cardData['prefilledFrom'] : null,
        ];
    }
}
