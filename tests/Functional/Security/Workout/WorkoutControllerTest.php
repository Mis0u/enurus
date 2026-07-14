<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\ExerciseRepository;
use App\Repository\RoutineRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkoutControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string USER_LBS = 'user-fixture-51-workout@test.com';

    private const string ROUTINE_OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string ROUTINE_OTHER = UserFixtures::USER_ROUTINE_OTHER;

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, '/fr/enregistre-seance', 'Enregistre ta séance | FitTracker');
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

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $workout = $workoutRepository->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ]
        );

        $this->assertNotNull($workout);
        $this->assertCount(1, $workout->workoutExercises);

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        $this->assertCount(1, $firstExercise->exerciseSets);
    }

    public function testExerciseBlockIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testExerciseBlockRedirectsToLoginWhenNotLogged(): void
    {
        $client = static::createClient();
        $exerciseId = $this->getExerciseId();

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseRedirects('/fr/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Connexion');
    }

    public function testWorkoutOwnerIsCurrentUser(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ]
        );

        $this->assertSame(self::USER, $workout->owner->email);
    }

    public function testWorkoutWithoutRoutineHasNullRoutine(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy([], [
            'id' => 'DESC',
        ]);

        $this->assertNull($workout->routine);
    }

    public function testWorkoutExercisePositionIsSaved(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy([], [
            'id' => 'DESC',
        ]);

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        $this->assertSame(0, $firstExercise->position);
    }

    public function testExerciseSetPositionIsSaved(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy([], [
            'id' => 'DESC',
        ]);

        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();

        /** @var ExerciseSet $firstExerciseSet */
        $firstExerciseSet = $firstExercise->exerciseSets->first();

        $this->assertSame(0, $firstExerciseSet->position);
    }

    public function testWorkoutWithMultipleExercisesHasCorrectPositions(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

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

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy([], [
            'id' => 'DESC',
        ]);

        $exercises = $workout->workoutExercises->toArray();
        usort($exercises, fn ($a, $b) => $a->position <=> $b->position);

        $this->assertSame(0, $exercises[0]->position);
        $this->assertSame(1, $exercises[1]->position);
    }

    public function testWorkoutWithMultipleSetsHasCorrectPositions(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

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

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy([], [
            'id' => 'DESC',
        ]);

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

    public function testExerciseBlockReturnsHtml(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
    }

    public function testExerciseBlockWithInvalidIdReturnsNotFound(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => '00000000-0000-0000-0000-000000000000',
                'index' => 0,
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testExerciseBlockContainsCorrectInputNames(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

        $crawler = $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][exercise]"]'));
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][exerciseSets][0][weight]"]'));
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][exerciseSets][0][reps]"]'));
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][position]"]'));
    }

    public function testExerciseBlockContainsTranslatedExerciseName(): void
    {
        $client = $this->login(self::USER);

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => (string) $exercise->id,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();

        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        $translatedName = $translator->trans($exercise->name, [], 'exercise', 'fr');

        $this->assertSelectorTextContains('h4', $translatedName);
    }

    public function testExerciseBlockContainsMusclesTags(): void
    {
        $client = $this->login(self::USER);

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/fr/workout/exercise-block',
            [
                'exerciseId' => (string) $exercise->id,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.muscle-tag, span[class*="text-[#f43f5e]"], span[class*="text-[#a855f7]"]')->count());
    }

    //DÉBUT NOTE
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

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ]
        );

        $this->assertSame($note, $workout->note);
    }

    public function testWorkoutWithoutNoteHasNullNote(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ]
        );

        $this->assertNull($workout->note);
    }
    //FIN NOTE

    //DÉBUT WORKOUT DATE

    public function testCheckDateReturnsExistsWhenWorkoutExists(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/check-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        assert(is_string($content));

        $data = json_decode($content, true);
        assert(is_array($data));

        $this->assertTrue($data['exists']);
        $this->assertSame(1, $data['count']);
    }

    public function testCheckDateReturnsNotExistsWhenNoWorkout(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/check-date',
            [
                'date' => new \DateTime('+1 day')->format('Y-m-d'),
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        assert(is_string($content));

        $data = json_decode($content, true);
        assert(is_array($data));

        $this->assertFalse($data['exists']);
        $this->assertSame(0, $data['count']);
    }

    public function testCheckDateIsNotAccessibleWhenNotLogged(): void
    {
        $client = static::createClient();

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/check-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
            ]
        );

        $this->assertResponseRedirects('/fr/');
    }

    public function testCheckDateReturnsCorrectCount(): void
    {
        $client = $this->login(self::USER);

        // Crée 2 séances le même jour
        $this->submitWorkout($client);
        $this->submitWorkout($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/check-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        assert(is_string($content));

        $data = json_decode($content, true);
        assert(is_array($data));

        $this->assertTrue($data['exists']);
        $this->assertSame(2, $data['count']);
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
                        'exercise' => $this->getExerciseId(),
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

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER_LBS,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ]
        );

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

        // Seul le placeholder ("Choisissez votre routine") doit être présent
        $this->assertCount(1, $options);
    }

    // -------------------------------------------------------------------------
    // RoutineExercisesBlockController
    // -------------------------------------------------------------------------

    public function testRoutineExercisesBlockRedirectsToLoginWhenNotLogged(): void
    {
        $client = static::createClient();

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/routine-exercises-block',
            [
                'routineId' => '019f0000-0000-7000-8000-000000000000',
            ]
        );

        $this->assertResponseRedirects('/fr/');
    }

    public function testRoutineExercisesBlockReturns400WhenRoutineIdMissing(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);

        $client->request(Request::METHOD_GET, '/fr/workout/routine-exercises-block');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRoutineExercisesBlockReturns404WhenRoutineNotFound(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/routine-exercises-block',
            [
                'routineId' => '019f0000-0000-7000-8000-000000000000',
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRoutineExercisesBlockReturns403WhenRoutineBelongsToOtherUser(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOtherUserRoutineId();

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/routine-exercises-block',
            [
                'routineId' => $routineId,
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRoutineExercisesBlockReturnsExercisesForOwnRoutine(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOwnerRoutineId($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/routine-exercises-block',
            [
                'routineId' => $routineId,
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('data-exercise-index', $content);
    }

    public function testRoutineExercisesBlockUsesStartIndex(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOwnerRoutineId($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/routine-exercises-block',
            [
                'routineId' => $routineId,
                'startIndex' => 5,
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('workout[workoutExercises][5][exercise]', $content);
    }

    public function testRoutineExercisesBlockReturnsCorrectExerciseCount(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOwnerRoutineId($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/workout/routine-exercises-block',
            [
                'routineId' => $routineId,
            ]
        );

        $this->assertResponseIsSuccessful();

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepository->find($routineId);
        $this->assertNotNull($routine);

        $expectedCount = $routine->routineExercises->count();

        $crawler = $client->getCrawler();
        $blocks = $crawler->filter('[data-exercise-index]');

        $this->assertCount($expectedCount, $blocks);
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS — à ajouter avec les autres helpers privés
    // -------------------------------------------------------------------------

    private function getOwnerRoutineId(KernelBrowser $client): string
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $owner = $userRepository->findOneBy([
            'email' => self::ROUTINE_OWNER,
        ]);
        $this->assertNotNull($owner);

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepository->findOneBy([
            'owner' => $owner,
            'name' => 'Push Day',
        ]);
        $this->assertNotNull($routine);
        $this->assertNotNull($routine->id);

        return $routine->id->toRfc4122();
    }

    private function getOtherUserRoutineId(): string
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $otherUser = $userRepository->findOneBy([
            'email' => self::ROUTINE_OTHER,
        ]);
        $this->assertNotNull($otherUser);

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepository->findOneBy([
            'owner' => $otherUser,
        ]);
        $this->assertNotNull($routine);
        $this->assertNotNull($routine->id);

        return $routine->id->toRfc4122();
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function submitWorkout(KernelBrowser $client, array $overrides = []): void
    {
        $exerciseId = $this->getExerciseId();

        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        $data = array_replace_recursive([
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
                ],
            ],
        ], $overrides);

        $client->request(
            Request::METHOD_POST,
            '/fr/enregistre-seance',
            $data
        );
    }

    private function getExerciseId(): string
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);
        return (string) $exercise->id;
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
}
