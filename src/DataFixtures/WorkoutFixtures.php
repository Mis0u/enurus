<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class WorkoutFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * Plages de poids réalistes par nombre de reps (en kg).
     * [reps => [minWeight, maxWeight, step]]
     */
    private const array WEIGHT_RANGES = [
        3 => [80.0, 120.0, 2.5],
        4 => [70.0, 110.0, 2.5],
        5 => [60.0, 100.0, 2.5],
        6 => [60.0, 90.0,  2.5],
        8 => [50.0, 80.0,  2.5],
        10 => [40.0, 70.0,  2.5],
        12 => [30.0, 60.0,  2.5],
        15 => [20.0, 50.0,  2.5],
    ];

    private const array REPS_OPTIONS = [3, 4, 5, 6, 8, 10, 12, 15];

    public function load(ObjectManager $manager): void
    {
        $exercises = $this->loadExercises();

        foreach (UserFixtures::WORKOUT_USERS as $userData) {
            /** @var User $user */
            $user = $this->getReference(
                \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, $userData['email']),
                User::class
            );

            $this->createWorkoutsForUser($user, $userData['count'], $userData['spreadDays'], $exercises, $manager);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ExerciseFixtures::class,
        ];
    }

    /**
     * @return Exercise[]
     */
    private function loadExercises(): array
    {
        $exercises = [];
        $index = 0;

        while ($this->hasReference(\sprintf('%s%d', ExerciseFixtures::REFERENCE_PREFIX, $index), Exercise::class)) {
            $exercises[] = $this->getReference(\sprintf('%s%d', ExerciseFixtures::REFERENCE_PREFIX, $index), Exercise::class);
            ++$index;
        }

        return $exercises;
    }

    /**
     * Crée N workouts répartis sur $spreadDays jours en remontant depuis aujourd'hui.
     *
     * @param Exercise[] $exercises
     */
    private function createWorkoutsForUser(
        User $user,
        int $workoutCount,
        int $spreadDays,
        array $exercises,
        ObjectManager $manager,
    ): void {
        $today = new \DateTimeImmutable('2026-05-14 23:59:59');
        $dates = $this->generateDates($today, $workoutCount, $spreadDays);

        foreach ($dates as $date) {
            $workout = new Workout();
            if ('user-fixture-26-workout@test.com' === $user->email) {
                $workout->note = 'Bonne séance, PR au développé couché. Fatigue sur les dips en fin de séance.';
            }
            $workout->owner = $user;
            $workout->performedAt = $date;
            $workout->duration = $this->randomDuration();

            $exerciseCount = random_int(3, 6);
            $pickedExercises = $this->pickRandom($exercises, $exerciseCount);

            foreach ($pickedExercises as $position => $exercise) {
                $workoutExercise = $this->createWorkoutExercise($workout, $exercise, $position);
                $workout->workoutExercises->add($workoutExercise);
            }

            $manager->persist($workout);
        }
    }

    /**
     * Génère $count dates uniques réparties aléatoirement sur $spreadDays jours.
     *
     * @return \DateTimeImmutable[]
     */
    private function generateDates(\DateTimeImmutable $today, int $count, int $spreadDays): array
    {
        $dates = [];
        $usedDays = [];

        $attempts = 0;
        $maxAttempts = $count * 10;

        while (\count($dates) < $count && $attempts < $maxAttempts) {
            ++$attempts;
            $daysAgo = random_int(0, $spreadDays - 1);
            $hour = random_int(7, 21);
            $minute = random_int(0, 59);

            if (isset($usedDays[$daysAgo]) && \count($usedDays) < $spreadDays) {
                continue;
            }

            $usedDays[$daysAgo] = true;
            $dates[] = $today->modify(\sprintf('-%d days', $daysAgo))->setTime($hour, $minute, 0);
        }

        usort($dates, static fn (\DateTimeImmutable $a, \DateTimeImmutable $b): int => $b <=> $a);

        return $dates;
    }

    private function createWorkoutExercise(Workout $workout, Exercise $exercise, int $position): WorkoutExercise
    {
        $workoutExercise = new WorkoutExercise();
        $workoutExercise->workout = $workout;
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = $position;

        $setCount = random_int(3, 5);

        for ($i = 0; $i < $setCount; ++$i) {
            $set = $this->createExerciseSet($workoutExercise, $i);
            $workoutExercise->exerciseSets->add($set);
        }

        return $workoutExercise;
    }

    private function createExerciseSet(WorkoutExercise $workoutExercise, int $position): ExerciseSet
    {
        $reps = self::REPS_OPTIONS[array_rand(self::REPS_OPTIONS)];
        $range = self::WEIGHT_RANGES[$reps];

        [$min, $max, $step] = $range;
        $steps = (int) (($max - $min) / $step);
        $weight = $min + (random_int(0, $steps) * $step);

        $set = new ExerciseSet();
        $set->workoutExercise = $workoutExercise;
        $set->position = $position;
        $set->reps = $reps;
        $set->weight = $weight;

        return $set;
    }

    /**
     * Pioche $count éléments aléatoires sans doublon dans un tableau.
     *
     * @template T
     * @param T[] $items
     * @return T[]
     */
    private function pickRandom(array $items, int $count): array
    {
        $count = min($count, \count($items));
        $keys = array_rand($items, $count);

        if (! \is_array($keys)) {
            $keys = [$keys];
        }

        return array_map(static fn (int|string $key) => $items[$key], $keys);
    }

    /**
     * Durée aléatoire réaliste entre 45 et 120 minutes, par tranche de 5.
     */
    private function randomDuration(): int
    {
        return random_int(9, 24) * 5;
    }
}
