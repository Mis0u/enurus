<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\ExerciseLastSessionRepository;
use App\Service\Utils\WeightConverterService;
use DateTimeImmutable;

/**
 * Données d'affichage d'une carte d'exercice ajoutée à une séance (sélecteur d'exercices en
 * création/édition, chargement d'une routine) : part de poids de corps et séries pré-remplies
 * avec la dernière performance, dans l'unité de l'utilisateur. Même format de séries que
 * l'édition d'une séance (`existingSets` de `_exercise_card.html.twig`).
 *
 * @phpstan-type PrefilledSet array{weight: float, reps: int, duration: ?int, distance: ?int, bodyweightShare: ?float}
 * @phpstan-type CardData array{cardBodyweightShare: ?float, existingSets: list<PrefilledSet>, prefilledFrom: ?DateTimeImmutable}
 */
class WorkoutExerciseCardDataBuilder
{
    public function __construct(
        private readonly ExerciseLastSessionRepository $lastSessionRepository,
        private readonly WeightConverterService $weightConverter,
        private readonly BodyweightSnapshotService $bodyweightSnapshotService,
    ) {
    }

    /**
     * @param Exercise[] $exercises
     * @return array<string, CardData> clé = identifiant de l'exercice
     */
    public function build(User $user, array $exercises): array
    {
        $lastSessions = $this->lastSessionRepository->findLastSessionByExercise($user, $exercises);

        $cardData = [];
        foreach ($exercises as $exercise) {
            $exerciseId = (string) $exercise->id;
            $bodyweightShare = $this->bodyweightShare($user, $exercise);
            $lastSession = $this->canPrefill($user, $exercise) ? ($lastSessions[$exerciseId] ?? null) : null;

            $cardData[$exerciseId] = [
                'cardBodyweightShare' => $bodyweightShare,
                'existingSets' => null !== $lastSession ? $this->prefilledSets($user, $lastSession, $bodyweightShare) : [],
                'prefilledFrom' => $lastSession?->performedAt,
            ];
        }

        return $cardData;
    }

    /**
     * Exercice au poids de corps sans poids renseigné : la carte doit rester vide pour rester
     * bloquée (`_exercise_card.html.twig` ne bloque jamais une carte qui contient des séries).
     */
    private function canPrefill(User $user, Exercise $exercise): bool
    {
        return null === $exercise->bodyweightPercent || null !== $user->bodyweightKg;
    }

    /**
     * Part calculée sur le poids de corps actuel : c'est lui qui sera figé sur les nouvelles
     * séries à l'enregistrement (`BodyweightSnapshotService::apply()`), pas celui de l'époque.
     */
    private function bodyweightShare(User $user, Exercise $exercise): ?float
    {
        if (null === $exercise->bodyweightPercent) {
            return null;
        }

        $shareKg = $this->bodyweightSnapshotService->shareKg($user->bodyweightKg, $exercise->bodyweightPercent);

        return round($this->weightConverter->convertToLbs($shareKg, $user->unitOfMeasure), 1);
    }

    /**
     * @return list<PrefilledSet>
     */
    private function prefilledSets(User $user, LastExerciseSession $lastSession, ?float $bodyweightShare): array
    {
        return array_map(
            fn (array $set): array => [
                'weight' => $this->weightConverter->convertToLbs($set['weight'], $user->unitOfMeasure),
                'reps' => $set['reps'],
                'duration' => $set['duration'],
                'distance' => $set['distance'],
                'bodyweightShare' => $bodyweightShare,
            ],
            $lastSession->sets,
        );
    }
}
