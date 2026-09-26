<?php

declare(strict_types=1);

namespace App\Service\Workout;

use App\Entity\Exercise;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Environment;

/**
 * Rend une carte d'exercice de séance ajoutée côté client (sélecteur d'exercices, restauration du
 * brouillon) : i18n, MuscleTags, séries et structure du formulaire restent rendus côté serveur,
 * jamais dupliqués en JS. L'`index` réel n'est connu que côté client (position dans la liste déjà
 * affichée) : rendu avec un placeholder littéral `__EXERCISE_INDEX__`, substitué en JS avant
 * insertion — même convention que `_template.html.twig` pour l'ajout de série.
 *
 * @phpstan-type RenderableCardData array{cardBodyweightShare: ?float, existingSets: list<array<string, mixed>>, prefilledFrom: ?\DateTimeImmutable}
 */
class WorkoutExerciseCardRenderer
{
    public const string INDEX_PLACEHOLDER = '__EXERCISE_INDEX__';

    public function __construct(
        private readonly Environment $twig,
        private readonly Security $security,
    ) {
    }

    /**
     * @param RenderableCardData $cardData
     * @param string $controllerName controller Stimulus consommateur de la carte (`exercise` en
     *                               création, `workout--edit--exercise` en édition)
     */
    public function render(Exercise $exercise, array $cardData, string $controllerName): string
    {
        return $this->twig->render('workout/create/_exercise_card.html.twig', [
            'exercise' => $exercise,
            'index' => self::INDEX_PLACEHOLDER,
            'controllerName' => $controllerName,
            'cardBodyweightShare' => $cardData['cardBodyweightShare'],
            'existingSets' => $cardData['existingSets'],
            'prefilledFrom' => $cardData['prefilledFrom'],
            'userHasBodyweight' => null !== $this->currentUser()->bodyweightKg,
        ]);
    }

    private function currentUser(): User
    {
        $user = $this->security->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('A workout exercise card is only rendered for a logged-in user.');
        }

        return $user;
    }
}
