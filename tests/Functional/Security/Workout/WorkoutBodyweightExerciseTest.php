<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\ExerciseSet;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\WorkoutRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class WorkoutBodyweightExerciseTest extends WebTestCase
{
    public function testSetWithoutExtraWeightSnapshotsUserBodyweight(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, 70.0);
        $exercise = $this->createBodyweightExercise($em, 70.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: '', reps: 12);

        $set = $this->getLatestExerciseSet($user);

        $this->assertSame(0.0, $set->weight);
        $this->assertSame(70.0, $set->bodyweightSnapshotKg);
    }

    public function testSetWithExtraWeightAddsItOnTopOfBodyweightSnapshot(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, 80.0);
        $exercise = $this->createBodyweightExercise($em, 100.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: 10, reps: 8);

        $set = $this->getLatestExerciseSet($user);

        $this->assertSame(10.0, $set->weight);
        $this->assertSame(80.0, $set->bodyweightSnapshotKg);
    }

    public function testEditingAnExistingSetNeverOverwritesTheOriginalSnapshot(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, 70.0);
        $exercise = $this->createBodyweightExercise($em, 70.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: '', reps: 12);

        $workout = $this->getLatestWorkout($user);

        // L'utilisateur met à jour son poids de corps après avoir loggé la séance.
        $user->bodyweightKg = 75.0;
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/seance/%s/modifier', $workout->id));
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, \sprintf('/fr/seance/%s/modifier', $workout->id), [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => $workout->performedAt->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => (string) $exercise->id,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => 5,
                                'reps' => 12,
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $set = $this->getLatestExerciseSet($user);

        // Le lest a bien été mis à jour...
        $this->assertSame(5.0, $set->weight);
        // ...mais le snapshot reste figé à sa valeur d'origine, jamais réécrit avec les 75kg actuels.
        $this->assertSame(70.0, $set->bodyweightSnapshotKg);
    }

    public function testEditFormPrefillsWeightInputWithRawExtraWeightOnly(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, 80.0);
        $exercise = $this->createBodyweightExercise($em, 100.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: 10, reps: 8);

        $workout = $this->getLatestWorkout($user);

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/seance/%s/modifier', $workout->id));

        // L'input "lest" ne doit jamais être pré-rempli avec le poids de corps + lest (ici
        // 80 + 10 = 90), seulement le lest brut soumis à la création — sinon un enregistrement
        // sans y toucher réinjecte le poids de corps dans le lest à chaque édition.
        $weightInput = $crawler->filter('input[name="workout[workoutExercises][0][exerciseSets][0][weight]"]');
        $this->assertSame('10', $weightInput->attr('value'));
    }

    public function testEditingBodyweightSetWithoutTouchingWeightInputDoesNotInflateStoredWeight(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, 80.0);
        $exercise = $this->createBodyweightExercise($em, 100.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: 10, reps: 8);

        $workout = $this->getLatestWorkout($user);

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/seance/%s/modifier', $workout->id));
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');
        $weightInputValue = $crawler->filter('input[name="workout[workoutExercises][0][exerciseSets][0][weight]"]')->attr('value');

        $client->request(Request::METHOD_POST, \sprintf('/fr/seance/%s/modifier', $workout->id), [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => $workout->performedAt->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => (string) $exercise->id,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => $weightInputValue,
                                'reps' => 8,
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $set = $this->getLatestExerciseSet($user);

        // Ré-éditer sans toucher au lest ne doit jamais faire grimper le poids stocké d'une
        // édition à l'autre (régression du bug où le total figé était réinjecté comme lest).
        $this->assertSame(10.0, $set->weight);
    }

    public function testCreatingWorkoutWithBodyweightExerciseWithoutBodyweightIsRejected(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, null);
        $exercise = $this->createBodyweightExercise($em, 70.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: '', reps: 12);

        $this->assertNull($this->workoutRepository()->findOneBy([
            'owner' => $user,
        ]));
    }

    public function testAddingNewSetToExistingBodyweightExerciseWithoutBodyweightIsRejectedOnEdit(): void
    {
        $client = static::createClient();
        $em = $this->em();
        $user = $this->createUserWithBodyweight($em, 70.0);
        $exercise = $this->createBodyweightExercise($em, 70.0);

        $client->loginUser($user);
        $this->submitBodyweightSet($client, $exercise, weight: '', reps: 12);

        $workout = $this->getLatestWorkout($user);

        // L'utilisateur supprime son poids de corps après avoir loggé la séance — via une
        // entité rechargée depuis le container courant : le kernel reboote entre chaque requête
        // du client de test, donc l'entity manager capturé en tout début de test est déjà obsolète.
        $em = $this->em();
        $reloadedUser = $em->getRepository(User::class)->find($user->id);
        if (! $reloadedUser instanceof User) {
            throw new \LogicException('User must exist after being created earlier in this test.');
        }
        $user = $reloadedUser;
        $user->bodyweightKg = null;
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, \sprintf('/fr/seance/%s/modifier', $workout->id));
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, \sprintf('/fr/seance/%s/modifier', $workout->id), [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => $workout->performedAt->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => (string) $exercise->id,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => '',
                                'reps' => 12,
                                'position' => 0,
                            ],
                            1 => [
                                'weight' => '',
                                'reps' => 10,
                                'position' => 1,
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $em = $this->em();
        $em->clear();
        $workout = $this->getLatestWorkout($user);
        /** @var WorkoutExercise $workoutExercise */
        $workoutExercise = $workout->workoutExercises->first();

        $this->assertCount(1, $workoutExercise->exerciseSets);
    }

    private function submitBodyweightSet(
        KernelBrowser $client,
        Exercise $exercise,
        string|int $weight,
        int $reps,
    ): void {
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
                        'exercise' => (string) $exercise->id,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => $weight,
                                'reps' => $reps,
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }

    private function createUserWithBodyweight(EntityManagerInterface $em, ?float $bodyweightKg): User
    {
        $user = new User();
        $user->email = \sprintf('bodyweight-test-%s@test.com', uniqid());
        $user->password = 'hashed';
        $user->nickname = 'BodyweightTestUser';
        $user->locale = 'fr';
        $user->lastLogin = new \DateTimeImmutable();
        $user->isVerified = true;
        $user->bodyweightKg = $bodyweightKg;

        $em->persist($user);
        $em->flush();

        return $user;
    }

    private function createBodyweightExercise(EntityManagerInterface $em, float $bodyweightPercent): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Bodyweight test exercise ' . uniqid();
        $exercise->isPublic = true;
        $exercise->measurementType = MeasurementType::WEIGHT_REPS;
        $exercise->bodyweightPercent = $bodyweightPercent;

        $em->persist($exercise);

        // `WorkoutExerciseRepository::findWithExercisesAndSets()` (page d'édition) fait un INNER
        // JOIN sur `exerciseMuscles` — un exercice sans muscle associé n'apparaîtrait jamais dans
        // le formulaire d'édition. `MuscleGroup` = données de référence issues des migrations
        // (jamais de fixtures), déjà présentes en base de test.
        $muscleGroup = $em->getRepository(MuscleGroup::class)->findAll()[0] ?? null;
        if (! $muscleGroup instanceof MuscleGroup) {
            throw new \LogicException('At least one MuscleGroup must exist (seeded by migrations).');
        }

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->exercise = $exercise;
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = MuscleTypeEnum::PRIMARY;
        $em->persist($exerciseMuscle);

        $em->flush();

        return $exercise;
    }

    private function getLatestWorkout(User $user): Workout
    {
        /** @var Workout $workout */
        $workout = $this->workoutRepository()->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ],
        );

        return $workout;
    }

    private function getLatestExerciseSet(User $user): ExerciseSet
    {
        $workout = $this->getLatestWorkout($user);

        /** @var WorkoutExercise $workoutExercise */
        $workoutExercise = $workout->workoutExercises->first();
        /** @var ExerciseSet $set */
        $set = $workoutExercise->exerciseSets->first();

        return $set;
    }

    private function workoutRepository(): WorkoutRepository
    {
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        return $workoutRepository;
    }

    private function em(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }
}
