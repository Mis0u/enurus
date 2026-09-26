<?php

declare(strict_types=1);

namespace App\Twig\Components\LiveComponent;

use App\Entity\Exercise;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Enum\Exercise\ExerciseCreationOriginEnum;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Entity\ExerciseSorterService;
use App\Service\Entity\MuscleGroupSorterService;
use App\Service\Workout\WorkoutExerciseCardDataBuilder;
use App\Service\Workout\WorkoutExerciseCardRenderer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('LiveComponent:ExerciseSelectorComponent:ExerciseSelectorComponent')]
final class ExerciseSelectorComponent
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    private const int HABITUAL_EXERCISES_LIMIT = 8;

    private const string HABITUAL_EXERCISES_PERIOD = '-60 days';

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public bool $isOpen = false;

    /**
     * Exercices cochés, dans l'ordre où ils l'ont été (LiveComponent ajoute chaque case cochée en
     * fin de tableau) — c'est l'ordre des cartes ajoutées à la séance. Lié en `norender` : cocher
     * ne déclenche aucun aller-retour serveur.
     *
     * @var list<string>
     */
    #[LiveProp(writable: true)]
    public array $selectedIds = [];

    /**
     * Groupe musculaire (id) => type de filtre actif — 1er clic = primaire, 2e clic = secondaire,
     * 3e clic = retiré, cf. `cycleMuscleFilter()`. Même comportement que le filtre muscle des
     * routines (`routine/create/_exercise_selector.html.twig`).
     *
     * @var array<string, value-of<MuscleTypeEnum>>
     */
    #[LiveProp(writable: true)]
    public array $muscleGroupFilters = [];

    /**
     * Nom du controller Stimulus consommateur des cartes rendues par `addSelectedExercises()`
     * (`exercise` en création, `workout--edit--exercise` en édition) — fixé une fois par la page
     * hôte au moment du `component(...)`, jamais modifié en cours de vie du composant.
     */
    #[LiveProp]
    public string $controllerName = 'exercise';

    /**
     * Vrai sur la page de création de séance, dont la saisie est gardée en brouillon
     * (`workout--draft` controller) : l'utilisateur peut partir créer un exercice manquant et
     * revenir sur sa séance, où l'exercice créé est ajouté (`ExerciseCreationOriginEnum`).
     */
    #[LiveProp]
    public bool $keepsWorkoutDraft = false;

    /**
     * @var list<Exercise>|null exercices proposés à l'utilisateur, chargés une fois par requête
     */
    private ?array $availableExercises = null;

    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly ExerciseSorterService $exerciseSorter,
        private readonly MuscleGroupSorterService $muscleGroupSorter,
        private readonly WorkoutExerciseCardDataBuilder $cardDataBuilder,
        private readonly WorkoutExerciseCardRenderer $cardRenderer,
        private readonly WorkoutStatsRepository $workoutStatsRepository,
    ) {
    }

    #[LiveAction]
    public function open(): void
    {
        $this->isOpen = true;
    }

    #[LiveAction]
    public function close(): void
    {
        $this->isOpen = false;
        $this->search = '';
        $this->muscleGroupFilters = [];
        $this->selectedIds = [];
    }

    /**
     * Rend les cartes des exercices cochés côté serveur (cf. WorkoutExerciseCardRenderer) et les
     * envoie directement dans l'événement, dans l'ordre de sélection.
     */
    #[LiveAction]
    public function addSelectedExercises(): void
    {
        $exercises = $this->selectedAddableExercises();

        if ([] === $exercises) {
            return;
        }

        $cardData = $this->cardDataBuilder->build($this->getUser(), $exercises);
        $htmls = array_map(
            fn (Exercise $exercise): string => $this->cardRenderer->render($exercise, $cardData[(string) $exercise->id], $this->controllerName),
            $exercises,
        );

        $this->close();
        $this->dispatchBrowserEvent('exercise:selected', [
            'htmls' => $htmls,
        ]);
    }

    /**
     * 1er clic = primaire, 2e clic = secondaire, 3e clic = retiré du filtre — même comportement
     * que le filtre muscle des routines.
     */
    #[LiveAction]
    public function cycleMuscleFilter(#[LiveArg] string $id): void
    {
        $current = $this->muscleGroupFilters[$id] ?? null;

        $next = match ($current) {
            null => MuscleTypeEnum::PRIMARY->value,
            MuscleTypeEnum::PRIMARY->value => MuscleTypeEnum::SECONDARY->value,
            default => null,
        };

        if (null === $next) {
            unset($this->muscleGroupFilters[$id]);
        } else {
            $this->muscleGroupFilters[$id] = $next;
        }
    }

    /**
     * @return Exercise[]
     */
    public function getFilteredExercises(): array
    {
        if (! $this->isOpen) {
            return [];
        }

        $sorted = $this->exerciseSorter->sortByName($this->availableExercises(), $this->getUser()->locale);

        if ('' !== $this->search) {
            $sorted = $this->filterByTranslatedName($sorted);
        }

        if ([] !== $this->muscleGroupFilters) {
            $sorted = $this->filterByMuscleGroups($sorted);
        }

        return $sorted;
    }

    /**
     * « Tes habituels » : les exercices les plus pratiqués ces 60 derniers jours, affichés par
     * ordre alphabétique en tête du sélecteur — seulement sans recherche ni filtre, qui
     * ciblent déjà un exercice précis.
     *
     * @return list<Exercise>
     */
    public function getHabitualExercises(): array
    {
        if (! $this->isOpen || '' !== $this->search || [] !== $this->muscleGroupFilters) {
            return [];
        }

        $habitualIds = $this->workoutStatsRepository->findMostFrequentExerciseIdsSince(
            $this->getUser(),
            new \DateTimeImmutable(self::HABITUAL_EXERCISES_PERIOD),
            self::HABITUAL_EXERCISES_LIMIT,
        );
        $habitualExercises = array_values(array_filter(
            $this->availableExercises(),
            static fn (Exercise $exercise): bool => \in_array((string) $exercise->id, $habitualIds, true),
        ));

        return $this->exerciseSorter->sortByName($habitualExercises, $this->getUser()->locale);
    }

    public function isSelected(Exercise $exercise): bool
    {
        return \in_array((string) $exercise->id, $this->selectedIds, true);
    }

    /**
     * @return list<MuscleGroup>
     */
    public function getMuscleGroups(): array
    {
        return $this->muscleGroupSorter->sortByName(
            $this->muscleGroupRepository->findAllOrderedByPosition(),
            $this->getUser()->locale,
        );
    }

    /**
     * @return array<string, string> paramètres de la route `app_exercise_create`
     */
    public function getCreateExerciseRouteParams(): array
    {
        return $this->keepsWorkoutDraft ? [
            'returnTo' => ExerciseCreationOriginEnum::WORKOUT->value,
        ] : [];
    }

    public function userHasBodyweight(): bool
    {
        return null !== $this->getUser()->bodyweightKg;
    }

    /**
     * Les identifiants cochés viennent du navigateur : seuls les exercices réellement proposés à
     * l'utilisateur (publics ou à lui, non archivés) sont retenus, jamais l'exercice privé d'un
     * autre. Un exercice au poids de corps reste exclu tant que l'utilisateur n'a pas de poids.
     *
     * @return list<Exercise> dans l'ordre de sélection
     */
    private function selectedAddableExercises(): array
    {
        $availableById = [];
        foreach ($this->availableExercises() as $exercise) {
            $availableById[(string) $exercise->id] = $exercise;
        }

        $exercises = [];
        foreach ($this->selectedIds as $selectedId) {
            $exercise = $availableById[$selectedId] ?? null;

            if (null !== $exercise && (null === $exercise->bodyweightPercent || $this->userHasBodyweight())) {
                $exercises[] = $exercise;
            }
        }

        return $exercises;
    }

    /**
     * @return list<Exercise>
     */
    private function availableExercises(): array
    {
        return $this->availableExercises ??= $this->exerciseRepository->findAvailableForUser($this->getUser());
    }

    private function getUser(): User
    {
        /** @var User $user */
        $user = $this->security->getUser();

        return $user;
    }

    /**
     * @param Exercise[] $exercises
     * @return Exercise[]
     */
    private function filterByTranslatedName(array $exercises): array
    {
        $target = $this->normalizeForSearch($this->search);

        return array_values(array_filter(
            $exercises,
            function (Exercise $exercise) use ($target): bool {
                $translatedName = $exercise->isPublic
                    ? $this->translator->trans($exercise->name, [], 'exercise')
                    : $exercise->name;

                return str_contains($this->normalizeForSearch($translatedName), $target);
            }
        ));
    }

    /**
     * Insensible à la casse et aux accents, pour que "developpe" trouve "Développé".
     */
    private function normalizeForSearch(string $value): string
    {
        $decomposed = \Normalizer::normalize($value, \Normalizer::FORM_D) ?: $value;
        $withoutDiacritics = preg_replace('/\p{Mn}/u', '', $decomposed) ?? $decomposed;

        return mb_strtolower($withoutDiacritics);
    }

    /**
     * @param Exercise[] $exercises
     * @return Exercise[]
     */
    private function filterByMuscleGroups(array $exercises): array
    {
        return array_values(array_filter(
            $exercises,
            function (Exercise $exercise): bool {
                foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
                    $wantedType = $this->muscleGroupFilters[(string) $exerciseMuscle->muscleGroup->id] ?? null;

                    if (null !== $wantedType && $wantedType === $exerciseMuscle->type->value) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }
}
