<?php

declare(strict_types=1);

namespace App\Twig\Components\LiveComponent;

use App\Entity\Exercise;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Service\Entity\ExerciseSorterService;
use App\Service\Entity\MuscleGroupSorterService;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Twig\Environment;

#[AsLiveComponent('LiveComponent:ExerciseSelectorComponent:ExerciseSelectorComponent')]
final class ExerciseSelectorComponent
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public bool $isOpen = false;

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
     * Nom du controller Stimulus consommateur de la carte rendue par `selectExercise()`
     * (`exercise` en création, `workout--edit--exercise` en édition) — fixé une fois par la page
     * hôte au moment du `component(...)`, jamais modifié en cours de vie du composant.
     */
    #[LiveProp]
    public string $controllerName = 'exercise';

    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly ExerciseSorterService $exerciseSorter,
        private readonly MuscleGroupSorterService $muscleGroupSorter,
        private readonly Environment $twig,
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
    }

    /**
     * Rend la carte exercice côté serveur (i18n, MuscleTags, structure du formulaire — jamais
     * dupliqué en JS) et l'envoie directement dans l'événement plutôt que de laisser le
     * controller Stimulus consommateur refaire un aller-retour HTTP pour la récupérer (ancien
     * `workout_exercise_block` / `workout_edit_exercise_block`, supprimés). L'`index` réel n'est
     * connu que côté client (position dans la liste déjà affichée) : on rend avec un placeholder
     * littéral `__EXERCISE_INDEX__`, substitué en JS avant insertion — même convention que
     * `_template.html.twig` pour l'ajout de série.
     */
    #[LiveAction]
    public function selectExercise(#[LiveArg] string $id): void
    {
        $exercise = $this->exerciseRepository->find($id);

        if (null === $exercise) {
            return;
        }

        $html = $this->twig->render('workout/create/_exercise_card.html.twig', [
            'exercise' => $exercise,
            'index' => '__EXERCISE_INDEX__',
            'controllerName' => $this->controllerName,
        ]);

        $this->search = '';
        $this->isOpen = false;
        $this->dispatchBrowserEvent('exercise:selected', [
            'html' => $html,
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

        $exercises = $this->exerciseRepository->findAvailableForUser($this->getUser());
        $sorted = $this->exerciseSorter->sortByName($exercises, $this->getUser()->locale);

        if ('' !== $this->search) {
            $sorted = $this->filterByTranslatedName($sorted);
        }

        if ([] !== $this->muscleGroupFilters) {
            $sorted = $this->filterByMuscleGroups($sorted);
        }

        return $sorted;
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
