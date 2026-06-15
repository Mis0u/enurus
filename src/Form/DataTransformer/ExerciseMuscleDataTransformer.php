<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\ExerciseMuscle;
use App\Entity\MuscleGroup;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Repository\MuscleGroupRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;

/**
 * Transforms submitted JSON string (from hidden input) into
 * a Collection<int, ExerciseMuscle> and vice versa.
 *
 * @implements DataTransformerInterface<Collection<int, ExerciseMuscle>, string>
 */
final class ExerciseMuscleDataTransformer implements DataTransformerInterface
{
    public function __construct(
        private readonly MuscleGroupRepository $muscleGroupRepository,
    ) {
    }

    /**
     * Collection<ExerciseMuscle> → JSON string
     *
     * @param Collection<int, ExerciseMuscle>|null $value
     */
    public function transform(mixed $value): string
    {
        if (null === $value || $value->isEmpty()) {
            return '';
        }

        $data = $value->map(
            static fn (ExerciseMuscle $em): array => [
                'id' => (string) $em->muscleGroup->id,
                'type' => $em->type->value,
            ]
        )->getValues();

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    /**
     * JSON string → Collection<ExerciseMuscle>
     */
    public function reverseTransform(mixed $value): Collection
    {
        if (null === $value || '' === $value) {
            return new ArrayCollection();
        }

        $items = $this->decodeJson($value);

        if (empty($items)) {
            return new ArrayCollection();
        }

        $muscles = new ArrayCollection();

        foreach ($items as $item) {
            $muscles->add($this->buildExerciseMuscle($item));
        }

        return $muscles;
    }

    /**
     * @return list<array{id: string, type: string}>
     */
    private function decodeJson(string $value): array
    {
        try {
            $decoded = json_decode($value, associative: true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new TransformationFailedException('Invalid JSON submitted for muscles.');
        }

        if (! is_array($decoded)) {
            throw new TransformationFailedException('Muscles data must be a JSON array.');
        }

        /** @var list<array{id: string, type: string}> $decoded */
        return $decoded;
    }

    /**
     * @param array{id: string, type: string} $item
     */
    private function buildExerciseMuscle(array $item): ExerciseMuscle
    {
        $muscleGroup = $this->muscleGroupRepository->find($item['id']);

        if (! $muscleGroup instanceof MuscleGroup) {
            throw new TransformationFailedException(
                sprintf('MuscleGroup with id "%s" not found.', $item['id'])
            );
        }

        $type = MuscleTypeEnum::tryFrom($item['type']);

        if (null === $type) {
            throw new TransformationFailedException(
                sprintf('Invalid MuscleTypeEnum value "%s".', $item['type'])
            );
        }

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = $type;

        return $exerciseMuscle;
    }
}
