<?php

declare(strict_types=1);

namespace App\Twig\Components\LiveComponent;

use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('LiveComponent:ExerciseSelectorComponent:ExerciseSelectorComponent')]
class ExerciseSelectorComponent
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: true)]
    public ?int $selectedExerciseId = null;

    #[LiveProp(writable: true)]
    public string $search = '';

    #[LiveProp(writable: true)]
    public bool $isOpen = false;

    public function __construct(
        private readonly ExerciseRepository $exerciseRepository,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
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
    }

    #[LiveAction]
    public function selectExercise(#[LiveArg] string $id): void
    {
        $this->selectedExerciseId = (int) $id;
        $this->search = '';
        $this->isOpen = false;
        $this->dispatchBrowserEvent('exercise:selected', [
            'id' => $id,
        ]);
    }

    public function getCurrentExercise(): ?Exercise
    {
        if (null === $this->selectedExerciseId) {
            return null;
        }

        return $this->exerciseRepository->find($this->selectedExerciseId);
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

        if ('' === $this->search) {
            return $exercises;
        }

        return $this->filterByTranslatedName($exercises);
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
}
