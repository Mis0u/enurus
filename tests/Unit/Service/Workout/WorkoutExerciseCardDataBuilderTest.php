<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Workout;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\ExerciseLastSessionRepository;
use App\Service\Utils\WeightConverterService;
use App\Service\Workout\BodyweightSnapshotService;
use App\Service\Workout\LastExerciseSession;
use App\Service\Workout\WorkoutExerciseCardDataBuilder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class WorkoutExerciseCardDataBuilderTest extends TestCase
{
    public function testPrefillsTheSetsOfTheLastSession(): void
    {
        $exercise = $this->exercise();
        $lastSession = new LastExerciseSession(new \DateTimeImmutable('2026-09-12 18:00'), [
            [
                'weight' => 80.0,
                'reps' => 10,
                'duration' => null,
                'distance' => null,
            ],
            [
                'weight' => 85.0,
                'reps' => 8,
                'duration' => null,
                'distance' => null,
            ],
        ]);

        $cardData = $this->builder([
            (string) $exercise->id => $lastSession,
        ])->build($this->user(), [$exercise]);

        self::assertEquals(new \DateTimeImmutable('2026-09-12 18:00'), $cardData[(string) $exercise->id]['prefilledFrom']);
        self::assertSame([
            [
                'weight' => 80.0,
                'reps' => 10,
                'duration' => null,
                'distance' => null,
                'bodyweightShare' => null,
            ],
            [
                'weight' => 85.0,
                'reps' => 8,
                'duration' => null,
                'distance' => null,
                'bodyweightShare' => null,
            ],
        ], $cardData[(string) $exercise->id]['existingSets']);
        self::assertNull($cardData[(string) $exercise->id]['cardBodyweightShare']);
    }

    public function testLeavesTheCardEmptyForAnExerciseNeverPerformed(): void
    {
        $exercise = $this->exercise();

        $cardData = $this->builder([])->build($this->user(), [$exercise]);

        self::assertSame([], $cardData[(string) $exercise->id]['existingSets']);
        self::assertNull($cardData[(string) $exercise->id]['prefilledFrom']);
    }

    public function testConvertsPrefilledWeightsToPounds(): void
    {
        $exercise = $this->exercise();
        $user = $this->user();
        $user->unitOfMeasure = UnitOfMeasureEnum::LBS;
        $lastSession = new LastExerciseSession(new \DateTimeImmutable('2026-09-12'), [
            [
                'weight' => 90.72,
                'reps' => 5,
                'duration' => null,
                'distance' => null,
            ],
        ]);

        $cardData = $this->builder([
            (string) $exercise->id => $lastSession,
        ])->build($user, [$exercise]);

        self::assertSame(200.0, $cardData[(string) $exercise->id]['existingSets'][0]['weight']);
    }

    public function testComputesTheBodyweightShareFromTheCurrentBodyweight(): void
    {
        $exercise = $this->exercise(bodyweightPercent: 70.0);
        $user = $this->user(bodyweightKg: 80.0);
        $lastSession = new LastExerciseSession(new \DateTimeImmutable('2026-09-12'), [
            [
                'weight' => 10.0,
                'reps' => 8,
                'duration' => null,
                'distance' => null,
            ],
        ]);

        $cardData = $this->builder([
            (string) $exercise->id => $lastSession,
        ])->build($user, [$exercise]);

        self::assertSame(56.0, $cardData[(string) $exercise->id]['cardBodyweightShare']);
        self::assertSame(56.0, $cardData[(string) $exercise->id]['existingSets'][0]['bodyweightShare']);
        self::assertSame(10.0, $cardData[(string) $exercise->id]['existingSets'][0]['weight']);
    }

    public function testNeverPrefillsABodyweightExerciseWhenTheUserHasNoBodyweight(): void
    {
        // Une carte contenant des séries n'est jamais considérée comme bloquée : pré-remplir
        // contournerait le blocage « poids de corps requis ».
        $exercise = $this->exercise(bodyweightPercent: 70.0);
        $lastSession = new LastExerciseSession(new \DateTimeImmutable('2026-09-12'), [
            [
                'weight' => 10.0,
                'reps' => 8,
                'duration' => null,
                'distance' => null,
            ],
        ]);

        $cardData = $this->builder([
            (string) $exercise->id => $lastSession,
        ])->build($this->user(bodyweightKg: null), [$exercise]);

        self::assertSame([], $cardData[(string) $exercise->id]['existingSets']);
        self::assertNull($cardData[(string) $exercise->id]['prefilledFrom']);
    }

    public function testKeepsDurationAndDistance(): void
    {
        $exercise = $this->exercise();
        $lastSession = new LastExerciseSession(new \DateTimeImmutable('2026-09-12'), [
            [
                'weight' => 0.0,
                'reps' => 0,
                'duration' => 60,
                'distance' => null,
            ],
            [
                'weight' => 0.0,
                'reps' => 0,
                'duration' => null,
                'distance' => 400,
            ],
        ]);

        $cardData = $this->builder([
            (string) $exercise->id => $lastSession,
        ])->build($this->user(), [$exercise]);

        self::assertSame(60, $cardData[(string) $exercise->id]['existingSets'][0]['duration']);
        self::assertSame(400, $cardData[(string) $exercise->id]['existingSets'][1]['distance']);
    }

    /**
     * @param array<string, LastExerciseSession> $lastSessions
     */
    private function builder(array $lastSessions): WorkoutExerciseCardDataBuilder
    {
        $repository = $this->createStub(ExerciseLastSessionRepository::class);
        $repository->method('findLastSessionByExercise')->willReturn($lastSessions);

        return new WorkoutExerciseCardDataBuilder($repository, new WeightConverterService(), new BodyweightSnapshotService());
    }

    private function exercise(?float $bodyweightPercent = null): Exercise
    {
        $exercise = new Exercise();
        $exercise->id = Uuid::v7();
        $exercise->bodyweightPercent = $bodyweightPercent;

        return $exercise;
    }

    private function user(?float $bodyweightKg = 75.0): User
    {
        $user = new User();
        $user->bodyweightKg = $bodyweightKg;

        return $user;
    }
}
