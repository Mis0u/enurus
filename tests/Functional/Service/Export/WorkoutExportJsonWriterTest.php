<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\Export;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Service\Export\WorkoutExportJsonWriter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutExportJsonWriterTest extends KernelTestCase
{
    public function testAnEmptyHistoryGivesAValidDocumentWithoutWorkouts(): void
    {
        $export = $this->decode($this->write([]));

        self::assertSame('kg', $export['weightUnit'] ?? null);
        self::assertIsString($export['exportedAt'] ?? null);
        self::assertSame([], $export['workouts'] ?? null);
    }

    public function testEveryWorkoutIsWrittenInOrderInAValidDocument(): void
    {
        $export = $this->decode($this->write([
            $this->workout('2026-01-05 08:00:00', 100.0),
            $this->workout('2026-01-07 18:30:00', 102.5),
        ]));

        self::assertIsArray($export['workouts'] ?? null);
        self::assertCount(2, $export['workouts']);
        self::assertSame('2026-01-05T08:00:00', $this->workoutAt($export['workouts'], 0)['performedAt'] ?? null);
        self::assertSame('2026-01-07T18:30:00', $this->workoutAt($export['workouts'], 1)['performedAt'] ?? null);
    }

    public function testTheDocumentIsCompact(): void
    {
        $json = $this->write([$this->workout('2026-01-05 08:00:00', 100.0)]);

        self::assertStringNotContainsString("\n", $json);
        self::assertStringNotContainsString('": ', $json);
    }

    /**
     * Les séances arrivent une par une (lecture par lots) : le writer ne doit pas exiger une liste.
     */
    public function testWorkoutsCanArriveOneByOne(): void
    {
        $workouts = (function (): \Generator {
            yield $this->workout('2026-01-05 08:00:00', 100.0);
            yield $this->workout('2026-01-07 18:30:00', 102.5);
        })();

        $export = $this->decode($this->write($workouts));

        self::assertIsArray($export['workouts'] ?? null);
        self::assertCount(2, $export['workouts']);
    }

    /**
     * @param iterable<Workout> $workouts
     */
    private function write(iterable $workouts): string
    {
        self::bootKernel();
        /** @var WorkoutExportJsonWriter $writer */
        $writer = static::getContainer()->get(WorkoutExportJsonWriter::class);

        $stream = fopen('php://memory', 'w+b') ?: throw new \LogicException('Unable to open a memory stream.');
        $writer->write(new User(), $workouts, $stream, 'fr');
        rewind($stream);
        $json = stream_get_contents($stream);
        fclose($stream);

        return false !== $json ? $json : throw new \LogicException('Unable to read the memory stream.');
    }

    /**
     * @return array<mixed>
     */
    private function decode(string $json): array
    {
        $export = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($export);

        return $export;
    }

    /**
     * @param array<mixed> $workouts
     *
     * @return array<mixed>
     */
    private function workoutAt(array $workouts, int $index): array
    {
        $workout = $workouts[$index] ?? null;
        self::assertIsArray($workout);

        return $workout;
    }

    private function workout(string $performedAt, float $weight): Workout
    {
        $exercise = new Exercise();
        $exercise->name = 'Développé couché maison';

        $set = new ExerciseSet();
        $set->position = 1;
        $set->weight = $weight;
        $set->reps = 8;

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 1;
        $workoutExercise->addExerciseSet($set);

        $workout = new Workout();
        $workout->performedAt = new \DateTimeImmutable($performedAt);
        $workout->addWorkoutExercise($workoutExercise);

        return $workout;
    }
}
