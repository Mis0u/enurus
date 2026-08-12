<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\ExerciseMuscle;
use App\Entity\ExerciseSet;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExerciseEditControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    // ── Accès ─────────────────────────────────────────────────────────────────

    public function testIsAccessibleWhenLogged(): void
    {
        $client = static::createClient();
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::OWNER,
        ]);
        $client->loginUser($user);

        $this->assertPageIsAccessibleWhenLogged(self::OWNER, $url, 'Modifier l\'exercice | Enurus', $client);
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->assertPageIsRedirectToLoginWhenNotLogged($url, $client);
    }

    public function testOtherUserCannotEditExercise(): void
    {
        $client = $this->login(self::OTHER_USER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testPublicExerciseCannotBeEdited(): void
    {
        $client = $this->login(self::OWNER);
        $publicExercise = $this->getExerciseByPublicFlag();
        $url = \sprintf('/fr/bibliotheque/exercice/%s/modifier', $publicExercise->id);

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Persistance ───────────────────────────────────────────────────────────

    public function testNameIsUpdated(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitEdit($client, $url, name: 'Reverse fly modifié');

        $exercise = $this->getExerciseByName('Reverse fly modifié');
        $this->assertNotNull($exercise);
        $this->assertSame('Reverse fly modifié', $exercise->name);
    }

    public function testDescriptionIsUpdated(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitEdit($client, $url, description: 'Nouvelle description.');

        $exercise = $this->getExerciseByName('Updated Exercise');
        $this->assertNotNull($exercise);
        $this->assertSame('Nouvelle description.', $exercise->description);
    }

    public function testDescriptionCanBeCleared(): void
    {
        $client = $this->login(self::OTHER_USER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);

        $this->submitEdit($client, $url, description: null);

        $exercise = $this->getExerciseByName('Updated Exercise');
        $this->assertNotNull($exercise);
        $this->assertNull($exercise->description);
    }

    public function testMusclesAreReplaced(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $newMuscleId = $this->getMuscleGroupId('name.lats');
        $muscles = json_encode([[
            'id' => $newMuscleId,
            'type' => 'primary',
        ]], JSON_THROW_ON_ERROR);

        $this->submitEdit($client, $url, muscles: $muscles);

        $exercise = $this->getExerciseByName('Updated Exercise');
        $this->assertNotNull($exercise);
        $this->assertCount(1, $exercise->exerciseMuscles);

        $first = $exercise->exerciseMuscles->first();
        $this->assertInstanceOf(\App\Entity\ExerciseMuscle::class, $first);
        $this->assertSame($newMuscleId, (string) $first->muscleGroup->id);
    }

    public function testMeasurementTypeCanBeChangedWhenExerciseHasNoRecordedSet(): void
    {
        $client = $this->login(self::OWNER);
        $owner = $this->getUserByEmail(self::OWNER);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exerciseId = (string) $this->createOwnedExercise($em, $owner, 'Unlocked exercise')->id;
        $url = \sprintf('/fr/bibliotheque/exercice/%s/modifier', $exerciseId);

        $this->submitEdit($client, $url, name: 'Unlocked exercise', measurementType: 'time');

        // static::getContainer() toujours après les requêtes HTTP : le kernel (et son EntityManager)
        // est rebooté à chaque requête du client de test, l'entité créée avant est donc détachée.
        $exercise = $this->getExerciseById($exerciseId);
        $this->assertSame(MeasurementType::TIME, $exercise->measurementType);

        $this->removeExerciseById($exerciseId);
    }

    public function testMeasurementTypeIsLockedOnceExerciseHasARecordedSet(): void
    {
        $client = $this->login(self::OWNER);
        $owner = $this->getUserByEmail(self::OWNER);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exercise = $this->createOwnedExercise($em, $owner, 'Locked exercise');
        $exerciseId = (string) $exercise->id;
        $this->addRecordedSet($em, $owner, $exercise);
        $url = \sprintf('/fr/bibliotheque/exercice/%s/modifier', $exerciseId);

        // Le champ est rendu `disabled` côté serveur (cf. ExerciseType::measurement_type_locked) :
        // même en soumettant explicitement "time", Symfony ignore un champ désactivé et garde la
        // valeur initiale de l'entité — verrouillage effectif, pas seulement visuel.
        $this->submitEdit($client, $url, name: 'Locked exercise', measurementType: 'time');

        $this->assertSame(MeasurementType::WEIGHT_REPS, $this->getExerciseById($exerciseId)->measurementType);

        $this->removeWorkoutsForExercise($exerciseId);
        $this->removeExerciseById($exerciseId);
    }

    public function testMeasurementTypeCanBeChangedToDistanceWhenExerciseHasNoRecordedSet(): void
    {
        $client = $this->login(self::OWNER);
        $owner = $this->getUserByEmail(self::OWNER);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exerciseId = (string) $this->createOwnedExercise($em, $owner, 'Unlocked distance exercise')->id;
        $url = \sprintf('/fr/bibliotheque/exercice/%s/modifier', $exerciseId);

        $this->submitEdit($client, $url, name: 'Unlocked distance exercise', measurementType: 'distance');

        $exercise = $this->getExerciseById($exerciseId);
        $this->assertSame(MeasurementType::DISTANCE, $exercise->measurementType);

        $this->removeExerciseById($exerciseId);
    }

    public function testOwnerIsUnchangedAfterEdit(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitEdit($client, $url);

        $exercise = $this->getExerciseByName('Updated Exercise');
        $this->assertNotNull($exercise);
        $this->assertSame(self::OWNER, $exercise->owner?->email);
    }

    // ── Validation ────────────────────────────────────────────────────────────

    public function testEmptyNameIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitEdit($client, $url, name: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEmptyMusclesIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitEdit($client, $url, muscles: '');

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->getExerciseByName('Updated Exercise'));
    }

    public function testMusclesWithoutPrimaryIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getEditUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $muscles = $this->buildMusclesJson(secondaryOnly: true);

        $this->submitEdit($client, $url, muscles: $muscles);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->getExerciseByName('Updated Exercise'));
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function getEditUrl(string $exerciseName): string
    {
        $exercise = $this->getExerciseByName($exerciseName);
        $this->assertNotNull($exercise);

        return \sprintf('/fr/bibliotheque/exercice/%s/modifier', $exercise->id);
    }

    private function getExerciseByName(string $name): ?Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);

        return $repository->findOneBy([
            'name' => $name,
        ]);
    }

    private function getExerciseByPublicFlag(): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $repository->findOneBy([
            'isPublic' => true,
        ]);

        return $exercise;
    }

    private function getMuscleGroupId(string $name): string
    {
        /** @var MuscleGroupRepository $repository */
        $repository = static::getContainer()->get(MuscleGroupRepository::class);

        /** @var MuscleGroup $muscleGroup */
        $muscleGroup = $repository->findOneBy([
            'name' => $name,
        ]);

        return (string) $muscleGroup->id;
    }

    private function buildMusclesJson(bool $secondaryOnly = false): string
    {
        $id = $this->getMuscleGroupId('name.chest');
        $type = $secondaryOnly ? 'secondary' : 'primary';

        return json_encode([[
            'id' => $id,
            'type' => $type,
        ]], JSON_THROW_ON_ERROR);
    }

    private function submitEdit(
        KernelBrowser $client,
        string $url,
        string $name = 'Updated Exercise',
        ?string $muscles = null,
        ?string $description = null,
        ?string $measurementType = null,
    ): void {
        $crawler = $client->request(Request::METHOD_GET, $url);
        $csrfToken = $crawler->filter('input[name="exercise[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, $url, [
            'exercise' => [
                '_token' => $csrfToken,
                'name' => $name,
                'muscles' => $muscles ?? $this->buildMusclesJson(),
                'description' => $description,
                'measurementType' => $measurementType,
            ],
        ]);
    }

    private function getExerciseById(string $id): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        /** @var Exercise $exercise */
        $exercise = $repository->find($id);

        return $exercise;
    }

    private function removeExerciseById(string $id): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->remove($this->getExerciseById($id));
        $em->flush();
    }

    private function removeWorkoutsForExercise(string $exerciseId): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $exercise = $this->getExerciseById($exerciseId);

        /** @var array<WorkoutExercise> $workoutExercises */
        $workoutExercises = $em->getRepository(WorkoutExercise::class)->findBy([
            'exercise' => $exercise,
        ]);

        foreach ($workoutExercises as $workoutExercise) {
            $em->remove($workoutExercise->workout);
        }

        $em->flush();
    }

    private function getUserByEmail(string $email): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        return $user;
    }

    private function createOwnedExercise(EntityManagerInterface $em, User $owner, string $name): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = $name;
        $exercise->owner = $owner;

        $muscleGroupId = $this->getMuscleGroupId('name.chest');
        /** @var MuscleGroupRepository $muscleGroupRepository */
        $muscleGroupRepository = static::getContainer()->get(MuscleGroupRepository::class);
        /** @var MuscleGroup $muscleGroup */
        $muscleGroup = $muscleGroupRepository->find($muscleGroupId);

        $exerciseMuscle = new ExerciseMuscle();
        $exerciseMuscle->exercise = $exercise;
        $exerciseMuscle->muscleGroup = $muscleGroup;
        $exerciseMuscle->type = MuscleTypeEnum::PRIMARY;
        $exercise->exerciseMuscles->add($exerciseMuscle);

        $em->persist($exercise);
        $em->persist($exerciseMuscle);
        $em->flush();

        return $exercise;
    }

    private function addRecordedSet(EntityManagerInterface $em, User $owner, Exercise $exercise): Workout
    {
        $workout = new Workout();
        $workout->owner = $owner;
        $workout->performedAt = new \DateTimeImmutable('-1 day');

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $workout->addWorkoutExercise($workoutExercise);

        $set = new ExerciseSet();
        $set->position = 0;
        $set->weight = 50.0;
        $set->reps = 10;
        $workoutExercise->addExerciseSet($set);

        $em->persist($workout);
        $em->flush();

        return $workout;
    }
}
