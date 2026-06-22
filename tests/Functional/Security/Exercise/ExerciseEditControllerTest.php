<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
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

        $this->assertPageIsAccessibleWhenLogged(self::OWNER, $url, 'Modifier l\'exercice | FitTracker', $client);
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
    ): void {
        $crawler = $client->request(Request::METHOD_GET, $url);
        $csrfToken = $crawler->filter('input[name="exercise[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, $url, [
            'exercise' => [
                '_token' => $csrfToken,
                'name' => $name,
                'muscles' => $muscles ?? $this->buildMusclesJson(),
                'description' => $description,
            ],
        ]);
    }
}
