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

        $this->createDashboardWorkouts($exercises, $manager);

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
     * Crée 1 workout aujourd'hui pour user-fixture-1-workout (palier 1 — tests de frontière).
     *
     * @param Exercise[] $exercises
     */
    private function createDashboardWorkouts(array $exercises, ObjectManager $manager): void
    {
        /** @var User $userSingle */
        $userSingle = $this->getReference(
            \sprintf('%s%s', UserFixtures::REFERENCE_PREFIX, UserFixtures::USER_DASHBOARD_SINGLE),
            User::class,
        );

        $workout = new Workout();
        $workout->owner = $userSingle;
        $workout->performedAt = new \DateTimeImmutable('today 10:00:00');
        $workout->duration = 60;

        $pickedExercises = $this->pickRandom($exercises, 3);
        foreach ($pickedExercises as $position => $exercise) {
            $workoutExercise = $this->createWorkoutExercise($workout, $exercise, $position);
            $workout->workoutExercises->add($workoutExercise);
        }

        $manager->persist($workout);
    }

    /**
     * Crée N workouts garantissant des séances dans la semaine courante et la semaine précédente,
     * le reste distribué aléatoirement sur $spreadDays jours en remontant depuis aujourd'hui.
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
        $today = new \DateTimeImmutable();
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
     * Génère $count dates uniques garantissant :
     *  - 2 séances minimum dans la semaine courante (lundi → aujourd'hui)
     *  - 3 séances minimum dans la semaine précédente (lundi → dimanche)
     *  - le reste distribué aléatoirement sur $spreadDays jours depuis aujourd'hui
     * Jamais de date dans le futur.
     *
     * @return \DateTimeImmutable[]
     */
    private function generateDates(\DateTimeImmutable $today, int $count, int $spreadDays): array
    {
        $dayOfWeek = (int) $today->format('N'); // 1=lundi, 7=dimanche

        // Lundi de la semaine courante
        $currentWeekStart = $today->modify(\sprintf('-%d days', $dayOfWeek - 1))->setTime(0, 0, 0);
        // Lundi de la semaine précédente
        $prevWeekStart = $currentWeekStart->modify('-7 days');

        /** @var array<string, true> $usedDays */
        $usedDays = [];
        /** @var \DateTimeImmutable[] $dates */
        $dates = [];

        // Phase 1 : séances garanties dans la semaine courante (lundi → aujourd'hui)
        $currentWeekSlots = min(2, $dayOfWeek, $count);
        $currentOffsets = range(0, $dayOfWeek - 1);
        shuffle($currentOffsets);
        foreach (\array_slice($currentOffsets, 0, $currentWeekSlots) as $daysFromMonday) {
            $date = $currentWeekStart
                ->modify(\sprintf('+%d days', $daysFromMonday))
                ->setTime(random_int(7, 21), random_int(0, 59), 0);
            $key = $date->format('Y-m-d');
            $usedDays[$key] = true;
            $dates[] = $date;
        }

        // Phase 2 : séances garanties dans la semaine précédente (lundi → dimanche)
        $prevWeekSlots = min(3, max(0, $count - \count($dates)));
        $prevOffsets = range(0, 6);
        shuffle($prevOffsets);
        foreach (\array_slice($prevOffsets, 0, $prevWeekSlots) as $daysFromPrevMonday) {
            $date = $prevWeekStart
                ->modify(\sprintf('+%d days', $daysFromPrevMonday))
                ->setTime(random_int(7, 21), random_int(0, 59), 0);
            $key = $date->format('Y-m-d');
            if (! isset($usedDays[$key])) {
                $usedDays[$key] = true;
                $dates[] = $date;
            }
        }

        // Phase 3 : reste aléatoire sur la fenêtre $spreadDays (jamais dans le futur)
        $attempts = 0;
        $maxAttempts = $count * 15;

        while (\count($dates) < $count && $attempts < $maxAttempts) {
            ++$attempts;
            $daysAgo = random_int(0, $spreadDays - 1);
            $date = $today
                ->modify(\sprintf('-%d days', $daysAgo))
                ->setTime(random_int(7, 21), random_int(0, 59), 0);
            $key = $date->format('Y-m-d');

            if (! isset($usedDays[$key])) {
                $usedDays[$key] = true;
                $dates[] = $date;
            }
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
