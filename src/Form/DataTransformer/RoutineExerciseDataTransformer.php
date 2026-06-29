<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Exercise;
use App\Entity\RoutineExercise;
use App\Repository\ExerciseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * @implements DataTransformerInterface<mixed, string>
 */
final readonly class RoutineExerciseDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private ExerciseRepository $exerciseRepository,
    ) {
    }

    public function transform(mixed $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        if (is_string($value)) {
            return $value;
        }

        if (! $value instanceof Collection) {
            return '';
        }

        if ($value->isEmpty()) {
            return '';
        }
        $data = $value->map(
            static function (mixed $re): array {
                if (! $re instanceof RoutineExercise) {
                    throw new \LogicException(sprintf('Expected %s, got %s.', RoutineExercise::class, get_debug_type($re)));
                }
                return [
                    'id' => (string) $re->exercise->id,
                    'position' => $re->position,
                ];
            }
        )->getValues();

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * JSON string → Collection<RoutineExercise>.
     * Utilisé à la soumission du formulaire.
     *
     * @return Collection<int, RoutineExercise>
     */
    public function reverseTransform(mixed $value): Collection
    {
        if ('' === $value || null === $value) {
            return new ArrayCollection();
        }

        try {
            /** @var list<array{id: string, position: int}> $items */
            $items = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TransformationFailedException('Invalid JSON for routine exercises.');
        }

        /** @var Collection<int, RoutineExercise> $collection */
        $collection = new ArrayCollection();

        foreach ($items as $item) {
            $exercise = $this->exerciseRepository->find($item['id']);

            if (! $exercise instanceof Exercise) {
                throw new TransformationFailedException(
                    sprintf('Exercise with id "%s" not found.', $item['id'])
                );
            }

            $collection->add($this->buildRoutineExercise($exercise, (int) $item['position']));
        }

        return $collection;
    }

    private function buildRoutineExercise(Exercise $exercise, int $position): RoutineExercise
    {
        $routineExercise = new RoutineExercise();
        $routineExercise->exercise = $exercise;
        $routineExercise->position = $position;

        return $routineExercise;
    }
}
