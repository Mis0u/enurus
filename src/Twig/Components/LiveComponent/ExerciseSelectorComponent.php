<?php

declare(strict_types=1);

namespace App\Twig\Components\LiveComponent;

use App\Entity\Exercise;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Service\Entity\ExerciseSorterService;
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

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public bool $isOpen = false;

    /**
     * @var list<string>
     */
    #[LiveProp(writable: true)]
    public array $muscleGroupFilters = [];

    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly MuscleGroupRepository $muscleGroupRepository,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
        private readonly ExerciseSorterService $exerciseSorter,
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

    #[LiveAction]
    public function selectExercise(#[LiveArg] string $id): void
    {
        $this->search = '';
        $this->isOpen = false;
        $this->dispatchBrowserEvent('exercise:selected', [
            'id' => $id,
        ]);
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
        return $this->muscleGroupRepository->findAllOrderedByPosition();
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
        $target = mb_strtolower($this->search);

        return array_values(array_filter(
            $exercises,
            function (Exercise $exercise) use ($target): bool {
                $translatedName = $exercise->isPublic
                    ? mb_strtolower($this->translator->trans($exercise->name, [], 'exercise'))
                    : mb_strtolower($exercise->name);

                return str_contains($translatedName, $target);
            }
        ));
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
                    if (\in_array((string) $exerciseMuscle->muscleGroup->id, $this->muscleGroupFilters, true)) {
                        return true;
                    }
                }

                return false;
            }
        ));
    }
}
