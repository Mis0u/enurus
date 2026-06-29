<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Routine;

use App\DataFixtures\RoutineFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Routine;
use App\Repository\ExerciseRepository;
use App\Repository\RoutineRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

final class RoutineEditControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string OTHER_USER = UserFixtures::USER_ROUTINE_OTHER;

    // -------------------------------------------------------------------------
    // ACCÈS
    // -------------------------------------------------------------------------

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $routeUrl = $this->getEditUrl();
        $this->assertPageIsRedirectToLoginWhenNotLogged($routeUrl, $client);
    }

    public function testIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, $this->getEditUrl());

        self::assertResponseIsSuccessful();
    }

    public function testIsForbiddenForOtherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $client->request(Request::METHOD_GET, $this->getEditUrl());

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // PERSISTENCE — cas valides
    // -------------------------------------------------------------------------

    public function testRoutineNameIsUpdated(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, name: 'Push Day Updated');

        self::assertResponseRedirects('/fr/mes-routines');

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);
        self::assertSame('Push Day Updated', $updated->name);
    }

    public function testRoutineDescriptionIsUpdated(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, description: 'Nouvelle description');

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);
        self::assertSame('Nouvelle description', $updated->description);
    }

    public function testRoutineDescriptionCanBeCleared(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, description: '');

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);
        self::assertNull($updated->description);
    }

    public function testRoutineExercisesAreReplaced(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, exercisesJson: $this->buildExercisesJson(2));

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);
        self::assertCount(2, $updated->routineExercises);
    }

    public function testRoutineExercisesHaveCorrectPositions(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, exercisesJson: $this->buildExercisesJson(3));

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);

        $positions = $updated->routineExercises
            ->map(static fn ($re) => $re->position)
            ->toArray();

        sort($positions);
        self::assertSame([1, 2, 3], $positions);
    }

    public function testRoutineOwnerDoesNotChangeAfterEdit(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, name: 'Push Day Renamed');

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $owner = $userRepo->findOneBy([
            'email' => self::OWNER,
        ]);
        self::assertNotNull($owner);

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);
        self::assertSame($owner, $updated->owner);
    }

    public function testEditWithSameNameIsAccepted(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, name: $routine->name);

        self::assertResponseRedirects('/fr/mes-routines');
    }

    // -------------------------------------------------------------------------
    // VALIDATION — cas invalides
    // -------------------------------------------------------------------------

    public function testEmptyNameIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, name: '');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testNameTooLongIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $longName = str_repeat('a', 101);
        $this->submitEdit($client, $routine, name: $longName);

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEmptyExercisesIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, exercisesJson: '');

        self::assertResponseRedirects('/fr/mes-routines/' . $routine->id . '/modifier');
    }

    public function testInvalidExerciseJsonIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->submitEdit($client, $routine, exercisesJson: 'not-valid-json');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testForeignExerciseIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $otherUser = $userRepo->findOneBy([
            'email' => self::OTHER_USER,
        ]);
        self::assertNotNull($otherUser);

        /** @var ExerciseRepository $exerciseRepo */
        $exerciseRepo = static::getContainer()->get(ExerciseRepository::class);
        $foreignExercise = $exerciseRepo->findOneBy([
            'name' => RoutineFixtures::EXERCISE_OTHER_USER,
        ]);

        if (null === $foreignExercise) {
            $this->markTestSkipped('Aucun exercice custom disponible pour l\'autre user.');
        }

        $json = json_encode([[
            'id' => (string) $foreignExercise->id,
            'position' => 1,
        ]], JSON_THROW_ON_ERROR);

        $this->submitEdit($client, $routine, exercisesJson: $json);

        $updated = $this->findRoutineById($routine->id);
        self::assertNotNull($updated);
        $firstExercise = $updated->routineExercises->first();
        self::assertNotFalse($firstExercise);
        self::assertNotSame($foreignExercise->id, $firstExercise->exercise->id);
    }

    // -------------------------------------------------------------------------
    // ISOLATION
    // -------------------------------------------------------------------------

    public function testOtherUserCannotEditRoutine(): void
    {
        $client = $this->login(self::OTHER_USER);
        $routine = $this->getOwnerRoutine();

        $client->request(Request::METHOD_POST, $this->getEditUrl($routine->id));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // UNICITÉ DU NOM
    // -------------------------------------------------------------------------

    public function testDuplicateNameForSameUserIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();

        // Crée une deuxième routine pour le même user
        $this->createSecondRoutine($client);

        // Tente de renommer la première avec le nom de la seconde
        $this->submitEdit($client, $routine, name: 'Second Routine');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    private function submitEdit(
        KernelBrowser $client,
        Routine $routine,
        ?string $name = null,
        ?string $description = null,
        ?string $exercisesJson = null,
    ): void {
        $url = $this->getEditUrl($routine->id);
        $crawler = $client->request(Request::METHOD_GET, $url);

        $csrfToken = $crawler->filter('input[name="routine[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, $url, [
            'routine' => [
                '_token' => $csrfToken,
                'name' => $name ?? $routine->name,
                'description' => $description,
                'exercises' => $exercisesJson ?? $this->buildExercisesJson(1),
            ],
        ]);
    }

    private function getEditUrl(?object $routineId = null): string
    {
        $id = $routineId ?? $this->getOwnerRoutine()->id;
        self::assertNotNull($id);

        if (! $id instanceof Uuid) {
            throw new \LogicException('Expected Uuid instance.');
        }

        return '/fr/mes-routines/' . $id->toRfc4122() . '/modifier';
    }

    private function getOwnerRoutine(): Routine
    {
        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $owner = $userRepo->findOneBy([
            'email' => self::OWNER,
        ]);
        self::assertNotNull($owner);

        $routine = $repo->findOneBy([
            'owner' => $owner,
            'name' => 'Push Day',
        ]);
        self::assertNotNull($routine);

        return $routine;
    }

    private function findRoutineById(mixed $id): ?Routine
    {
        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);

        return $repo->find($id);
    }

    private function buildExercisesJson(int $count): string
    {
        /** @var ExerciseRepository $repo */
        $repo = static::getContainer()->get(ExerciseRepository::class);
        $exercises = $repo->findBy([
            'isPublic' => true,
        ], limit: $count);

        $data = array_map(
            static fn ($exercise, int $i): array => [
                'id' => (string) $exercise->id,
                'position' => $i + 1,
            ],
            $exercises,
            array_keys($exercises),
        );

        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function createSecondRoutine(KernelBrowser $client): void
    {
        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-routines/creer');
        $csrfToken = $crawler->filter('input[name="routine[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, '/fr/mes-routines/creer', [
            'routine' => [
                '_token' => $csrfToken,
                'name' => 'Second Routine',
                'exercises' => $this->buildExercisesJson(1),
            ],
        ]);
    }
}
