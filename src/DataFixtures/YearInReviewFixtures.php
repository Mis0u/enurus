<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Service\Badge\BadgeSyncService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * **Dev uniquement** : une année 2026 complète pour prévisualiser le résumé « Ton année » (email,
 * bandeau, page, écrans) avant le 16 décembre, avec `YEAR_IN_REVIEW_FAKE_NOW` puis
 * `app:year-in-review:generate 2026 --user=user-fixture-year-in-review@test.com`.
 *
 * Séances **volontairement futures** (jusqu'au 15 décembre, plus deux après pour vérifier qu'elles
 * sont ignorées) et dates fixes, jamais aléatoires : chaque écran a un contenu prévisible. Jamais
 * chargée en test, où ces séances futures fausseraient les comptages d'autres tests.
 */
final class YearInReviewFixtures extends Fixture implements DependentFixtureInterface
{
    public const string USER_EMAIL = 'user-fixture-year-in-review@test.com';

    private const int YEAR = 2026;

    private const int PULL_UP_BODYWEIGHT_KG = 78;

    /**
     * Index des exercices publics dans `Exercises.json` (références `ExerciseFixtures`).
     */
    private const int BENCH_PRESS = 0;

    private const int PULL_UP = 11;

    private const int BARBELL_ROW = 13;

    private const int DEADLIFT = 18;

    private const int MILITARY_PRESS = 22;

    private const int LATERAL_RAISE = 24;

    private const int BARBELL_CURL = 29;

    private const int SKULL_CRUSHER = 40;

    private const int BARBELL_SQUAT = 47;

    private const int LEG_PRESS = 49;

    /**
     * Charge de départ (kg) en janvier, +25 % en fin d'année : de quoi battre des records réguliers.
     */
    private const array STARTING_WEIGHT_KG = [
        self::BENCH_PRESS => 80.0,
        self::PULL_UP => 0.0,
        self::BARBELL_ROW => 70.0,
        self::DEADLIFT => 140.0,
        self::MILITARY_PRESS => 45.0,
        self::LATERAL_RAISE => 10.0,
        self::BARBELL_CURL => 30.0,
        self::SKULL_CRUSHER => 30.0,
        self::BARBELL_SQUAT => 110.0,
        self::LEG_PRESS => 120.0,
    ];

    private const float YEARLY_PROGRESSION = 0.25;

    /**
     * Push le lundi, pull le mercredi, jambes le vendredi (et le samedi en mars) — le développé
     * couché revient en fin de séance jambes, pour en faire clairement l'exercice fétiche.
     */
    private const array PROGRAM_BY_WEEKDAY = [
        1 => [self::BENCH_PRESS, self::MILITARY_PRESS, self::LATERAL_RAISE, self::SKULL_CRUSHER],
        3 => [self::DEADLIFT, self::PULL_UP, self::BARBELL_ROW, self::BARBELL_CURL],
        4 => [self::DEADLIFT, self::PULL_UP, self::BARBELL_ROW, self::BARBELL_CURL],
        5 => [self::BARBELL_SQUAT, self::LEG_PRESS, self::BENCH_PRESS],
        6 => [self::BARBELL_SQUAT, self::LEG_PRESS, self::BENCH_PRESS],
    ];

    /**
     * Lundis des semaines sans séance : une en avril, trois en août, une en octobre.
     */
    private const array EMPTY_WEEKS = ['2026-04-13', '2026-08-03', '2026-08-10', '2026-08-17', '2026-10-19'];

    private const array WORKOUTS_AFTER_THE_PERIOD = ['2026-12-17 18:30:00', '2026-12-18 18:30:00'];

    /**
     * Humeur saisie sur environ 60 % des séances (null = pas saisie), en rotation.
     */
    private const array MOOD_ROTATION = [
        WorkoutMoodEnum::EN_FORME, null, WorkoutMoodEnum::NORMAL, WorkoutMoodEnum::EN_FORME, null,
        WorkoutMoodEnum::GROSSE_PERF, WorkoutMoodEnum::EN_FORME, null, WorkoutMoodEnum::FATIGUE, null,
    ];

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly BadgeSyncService $badgeSyncService,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        if ('dev' !== $this->environment) {
            return;
        }

        $user = $this->createUser();
        $manager->persist($user);

        foreach ($this->workoutDates() as $index => $performedAt) {
            $manager->persist($this->createWorkout($user, $performedAt, self::MOOD_ROTATION[$index % \count(self::MOOD_ROTATION)]));
        }

        $manager->flush();
        $this->badgeSyncService->sync($user);
    }

    public function getDependencies(): array
    {
        return [ExerciseFixtures::class];
    }

    private function createUser(): User
    {
        $user = new User();
        $user->email = self::USER_EMAIL;
        $user->nickname = 'TonAnnee2026';
        $user->createdBy = $user;
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();
        $user->password = $this->passwordHasher->hashPassword($user, 'pass_1234');
        $user->isVerified = true;
        $user->guidedTourSeen = true;
        // Inscrit avant l'année résumée, comme un vrai utilisateur fidèle.
        $user->createdAt = new \DateTimeImmutable('2025-12-01 09:00:00');

        return $user;
    }

    /**
     * @return list<\DateTimeImmutable>
     */
    private function workoutDates(): array
    {
        $dates = [];
        $lastDay = new \DateTimeImmutable(\sprintf('%d-12-15', self::YEAR));

        for ($day = new \DateTimeImmutable(\sprintf('%d-01-01', self::YEAR)); $day <= $lastDay; $day = $day->modify('+1 day')) {
            if ($this->isTrainingDay($day)) {
                $dates[] = $day->setTime(6 === (int) $day->format('N') ? 10 : 18, 30);
            }
        }

        return [...$dates, ...array_map(static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date), self::WORKOUTS_AFTER_THE_PERIOD)];
    }

    /**
     * Lundi, mercredi, vendredi, plus le samedi en mars (mois le plus chargé). Le 1er janvier
     * tombe un jeudi : séance pull ce jour-là pour démarrer l'année.
     */
    private function isTrainingDay(\DateTimeImmutable $day): bool
    {
        $weekday = (int) $day->format('N');
        $monday = $day->modify(\sprintf('-%d days', $weekday - 1))->format('Y-m-d');

        if (\in_array($monday, self::EMPTY_WEEKS, true)) {
            return false;
        }

        return \in_array($weekday, [1, 3, 5], true)
            || (6 === $weekday && 3 === (int) $day->format('n'))
            || '01-01' === $day->format('m-d');
    }

    private function createWorkout(User $user, \DateTimeImmutable $performedAt, ?WorkoutMoodEnum $mood): Workout
    {
        $workout = new Workout();
        $workout->owner = $user;
        $workout->performedAt = $performedAt;
        $workout->duration = 70;
        $workout->mood = $mood;

        $program = self::PROGRAM_BY_WEEKDAY[(int) $performedAt->format('N')]
            ?? throw new \LogicException('No training program for this weekday.');

        foreach ($program as $position => $exerciseIndex) {
            $workout->addWorkoutExercise($this->createWorkoutExercise($exerciseIndex, $position, $this->progressOf($performedAt)));
        }

        return $workout;
    }

    private function createWorkoutExercise(int $exerciseIndex, int $position, float $progress): WorkoutExercise
    {
        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $this->getReference(\sprintf('%s%d', ExerciseFixtures::REFERENCE_PREFIX, $exerciseIndex), Exercise::class);
        $workoutExercise->position = $position;
        $weight = $this->roundToPlate(self::STARTING_WEIGHT_KG[$exerciseIndex] * (1 + self::YEARLY_PROGRESSION * $progress));

        foreach ([10, 8, 8, 6] as $setPosition => $reps) {
            $workoutExercise->addExerciseSet($this->createSet($exerciseIndex, $setPosition, $reps, $weight));
        }

        return $workoutExercise;
    }

    private function createSet(int $exerciseIndex, int $position, int $reps, float $weight): ExerciseSet
    {
        $set = new ExerciseSet();
        $set->position = $position;
        $set->reps = $reps;
        $set->weight = $weight;

        if (self::PULL_UP === $exerciseIndex) {
            $set->bodyweightSnapshotKg = self::PULL_UP_BODYWEIGHT_KG;
        }

        return $set;
    }

    /**
     * 0 le 1er janvier, 1 le 31 décembre.
     */
    private function progressOf(\DateTimeImmutable $performedAt): float
    {
        return (int) $performedAt->format('z') / 364;
    }

    private function roundToPlate(float $weight): float
    {
        return round($weight / 2.5) * 2.5;
    }
}
