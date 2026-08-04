<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\DataFixtures\UserFixtures;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class WorkoutControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string USER_LBS = 'user-fixture-51-workout@test.com';

    private const string ROUTINE_OWNER = UserFixtures::USER_ROUTINE_OWNER;

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, '/fr/enregistre-seance', 'Enregistre ta séance | Enurus');
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged('/fr/enregistre-seance');
    }

    public function testValidWorkoutIsPersistedInDatabase(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $workout = $this->getLatestWorkout(self::USER);

        $this->assertCount(1, $workout->workoutExercises);

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        $this->assertCount(1, $firstExercise->exerciseSets);
    }

    public function testWorkoutOwnerIsCurrentUser(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $workout = $this->getLatestWorkout(self::USER);

        $this->assertSame(self::USER, $workout->owner->email);
    }

    public function testPerformedAtIsStampedWithCurrentTimeNotMidnight(): void
    {
        $client = $this->login(self::USER);
        $before = new \DateTimeImmutable();
        $this->submitWorkout($client);
        $after = new \DateTimeImmutable();

        $workout = $this->getLatestWorkout(self::USER);

        // Pas de sélecteur d'heure dans le formulaire — sans ce comportement, performedAt tomberait
        // à minuit et plusieurs séances loguées le même jour ne pourraient plus se départager par
        // ordre d'ajout dans le tri `performedAt DESC` (cf. WorkoutController::stampCurrentTime()).
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $workout->performedAt->getTimestamp());
        $this->assertLessThanOrEqual($after->getTimestamp(), $workout->performedAt->getTimestamp());
    }

    public function testWorkoutWithoutRoutineHasNullRoutine(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $workout = $this->getLatestWorkoutForAnyUser();

        $this->assertNull($workout->routine);
    }

    public function testWorkoutExercisePositionIsSaved(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $workout = $this->getLatestWorkoutForAnyUser();

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        $this->assertSame(0, $firstExercise->position);
    }

    public function testExerciseSetPositionIsSaved(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $workout = $this->getLatestWorkoutForAnyUser();

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        /** @var ExerciseSet $firstExerciseSet */
        $firstExerciseSet = $firstExercise->exerciseSets->first();

        $this->assertSame(0, $firstExerciseSet->position);
    }

    public function testWorkoutWithMultipleExercisesHasCorrectPositions(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = WorkoutTestHelper::getPublicExerciseId($this->exerciseRepository());

        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, '/fr/enregistre-seance', [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => new \DateTime('today')->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => $exerciseId,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => 80,
                                'reps' => 10,
                                'position' => 0,
                            ],
                        ],
                    ],
                    1 => [
                        'exercise' => $exerciseId,
                        'position' => 1,
                        'exerciseSets' => [
                            0 => [
                                'weight' => 60,
                                'reps' => 12,
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $workout = $this->getLatestWorkoutForAnyUser();

        $exercises = $workout->workoutExercises->toArray();
        usort($exercises, fn ($a, $b) => $a->position <=> $b->position);

        $this->assertSame(0, $exercises[0]->position);
        $this->assertSame(1, $exercises[1]->position);
    }

    public function testWorkoutWithMultipleSetsHasCorrectPositions(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = WorkoutTestHelper::getPublicExerciseId($this->exerciseRepository());

        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, '/fr/enregistre-seance', [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => (new \DateTime('today'))->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => $exerciseId,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => 80,
                                'reps' => 10,
                                'position' => 0,
                            ],
                            1 => [
                                'weight' => 90,
                                'reps' => 8,
                                'position' => 1,
                            ],
                            2 => [
                                'weight' => 100,
                                'reps' => 6,
                                'position' => 2,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $workout = $this->getLatestWorkoutForAnyUser();

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();
        $sets = $firstExercise->exerciseSets->toArray();
        usort($sets, fn ($a, $b) => $a->position <=> $b->position);

        $this->assertSame(0, $sets[0]->position);
        $this->assertSame(1, $sets[1]->position);
        $this->assertSame(2, $sets[2]->position);
    }

    public function testFutureDateIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client, [
            'workout' => [
                'performedAt' => (new \DateTime('+1 day'))->format('Y-m-d'),
            ],
        ]);

        $this->assertResponseRedirects('/fr/enregistre-seance');
    }

    public function testEmptyWeightIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client, $this->workoutDatas('', 10));

        $this->assertResponseRedirects('/fr/enregistre-seance');
    }

    public function testEmptyRepsIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client, $this->workoutDatas(80, '', 0));

        $this->assertResponseRedirects('/fr/enregistre-seance');
    }

    public function testNegativeWeightIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client, $this->workoutDatas(-10, 10));

        $this->assertResponseRedirects('/fr/enregistre-seance');
    }

    public function testNegativeRepsIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client, $this->workoutDatas(80, -5));

        $this->assertResponseRedirects('/fr/enregistre-seance');
    }

    public function testEmptyPerformedAtIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client, [
            'workout' => [
                'performedAt' => '',
            ],
        ]);

        $this->assertResponseRedirects('/fr/enregistre-seance');
    }

    public function testWorkoutWithNoteIsPersisted(): void
    {
        $note = 'Super séance, nouveau record sur le développé couché !';
        $client = $this->login(self::USER);
        $this->submitWorkout($client, [
            'workout' => [
                'note' => $note,
            ],
        ]);
        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $workout = $this->getLatestWorkout(self::USER);

        $this->assertSame($note, $workout->note);
    }

    public function testWorkoutWithoutNoteHasNullNote(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $workout = $this->getLatestWorkout(self::USER);

        $this->assertNull($workout->note);
    }

    public function testWeightIsConvertedToKgOnSubmissionForLbsUser(): void
    {
        $client = $this->login(self::USER_LBS);

        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        // L'user saisit 200 lbs
        $client->request(Request::METHOD_POST, '/fr/enregistre-seance', [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => new \DateTime('today')->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => WorkoutTestHelper::getPublicExerciseId($this->exerciseRepository()),
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => 200,
                                'reps' => 10,
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $workout = $this->getLatestWorkout(self::USER_LBS);

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        /** @var ExerciseSet $firstSet */
        $firstSet = $firstExercise->exerciseSets->first();

        // 200 lbs / 2.20462 = ~90.72 kg
        $this->assertEqualsWithDelta(90.72, $firstSet->weight, 0.1, 'Le poids doit être converti en kg en base');
    }

    public function testRoutineSelectOnlyContainsOwnerRoutines(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');

        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('select#workout_routine option')->each(
            fn ($node) => trim($node->text())
        );

        $this->assertContains('Push Day', $options);
    }

    public function testRoutineSelectDoesNotContainOtherUserRoutines(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');

        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('select#workout_routine option')->each(
            fn ($node) => trim($node->text())
        );

        $this->assertNotContains('Other User Routine', $options);
    }

    public function testRoutineSelectIsEmptyWhenUserHasNoRoutine(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');

        $this->assertResponseIsSuccessful();

        $options = $crawler->filter('select#workout_routine option');

        // Seul le placeholder ("Choisis ta routine") doit être présent
        $this->assertCount(1, $options);
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $overrides
     */
    private function submitWorkout(KernelBrowser $client, array $overrides = []): void
    {
        WorkoutTestHelper::submitWorkout($client, $this->exerciseRepository(), $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function workoutDatas(string|int $weight = '', string|int $reps = '', int $position = 0): array
    {
        return [
            'workout' => [
                'workoutExercises' => [
                    0 => [
                        'exerciseSets' => [
                            0 => [
                                'weight' => $weight,
                                'reps' => $reps,
                                'position' => $position,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getLatestWorkout(string $email): Workout
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        /** @var Workout $workout */
        $workout = $this->workoutRepository()->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ]
        );

        return $workout;
    }

    private function getLatestWorkoutForAnyUser(): Workout
    {
        /** @var Workout $workout */
        $workout = $this->workoutRepository()->findOneBy([], [
            'id' => 'DESC',
        ]);

        return $workout;
    }

    private function exerciseRepository(): ExerciseRepository
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        return $exerciseRepository;
    }

    private function workoutRepository(): WorkoutRepository
    {
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        return $workoutRepository;
    }
}
