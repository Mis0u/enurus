<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Routine;

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

final class RoutineCreateControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string ROUTE_FR = '/fr/mes-routines/creer';

    // -------------------------------------------------------------------------
    // ACCÈS
    // -------------------------------------------------------------------------

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::ROUTE_FR);
    }

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(
            self::OWNER,
            self::ROUTE_FR,
            'Créer une routine | FitTracker',
        );
    }

    // -------------------------------------------------------------------------
    // PERSIST — cas valides
    // -------------------------------------------------------------------------

    public function testValidRoutineIsPersistedInDatabase(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Push Day A', $this->buildExercisesJson(1));

        self::assertResponseRedirects('/fr/mes-routines');

        $routine = $this->findRoutine('Push Day A');
        self::assertNotNull($routine);
    }

    public function testRoutineOwnerIsCurrentUser(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Pull Day', $this->buildExercisesJson(1));

        $routine = $this->findRoutine('Pull Day');
        self::assertNotNull($routine);

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy([
            'email' => self::OWNER,
        ]);
        self::assertNotNull($user);
        self::assertSame($user, $routine->owner);
    }

    public function testRoutineHasCorrectExerciseCount(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Leg Day', $this->buildExercisesJson(2));

        $routine = $this->findRoutine('Leg Day');
        self::assertNotNull($routine);
        self::assertCount(2, $routine->routineExercises);
    }

    public function testRoutineExercisesHaveCorrectPositions(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Full Body', $this->buildExercisesJson(2));

        $routine = $this->findRoutine('Full Body');
        self::assertNotNull($routine);

        $positions = $routine->routineExercises->map(
            static fn ($re) => $re->position
        )->toArray();

        self::assertContains(1, $positions);
        self::assertContains(2, $positions);
    }

    public function testRoutineWithDescriptionIsPersisted(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Push Day B', $this->buildExercisesJson(1), 'Séance lourde 3-5 reps');

        $routine = $this->findRoutine('Push Day B');
        self::assertNotNull($routine);
        self::assertSame('Séance lourde 3-5 reps', $routine->description);
    }

    public function testRoutineWithoutDescriptionHasNullDescription(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Push Day C', $this->buildExercisesJson(1));

        $routine = $this->findRoutine('Push Day C');
        self::assertNotNull($routine);
        self::assertNull($routine->description);
    }

    // -------------------------------------------------------------------------
    // VALIDATION — cas invalides
    // -------------------------------------------------------------------------

    public function testEmptyNameIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, '', $this->buildExercisesJson(1));

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull($this->findRoutine(''));
    }

    public function testEmptyExercisesIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'No Exercise Routine', '');

        self::assertResponseRedirects('/fr/mes-routines/creer');
        self::assertNull($this->findRoutine('No Exercise Routine'));
    }

    public function testInvalidExerciseJsonIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Bad Json Routine', 'not-valid-json');

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull($this->findRoutine('Bad Json Routine'));
    }

    // -------------------------------------------------------------------------
    // ISOLATION
    // -------------------------------------------------------------------------

    public function testRoutineIsIsolatedFromOtherUser(): void
    {
        $client = $this->login(self::OWNER);
        $this->submitCreate($client, 'Isolated Routine', $this->buildExercisesJson(1));

        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $otherUser = $userRepo->findOneBy([
            'email' => UserFixtures::USER_ROUTINE_OTHER,
        ]);
        self::assertNotNull($otherUser);

        /** @var RoutineRepository $routineRepo */
        $routineRepo = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepo->findOneBy([
            'name' => 'Isolated Routine',
            'owner' => $otherUser,
        ]);
        self::assertNull($routine);
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    private function submitCreate(
        KernelBrowser $client,
        string $name,
        string $exercisesJson,
        ?string $description = null,
    ): void {
        $crawler = $client->request(Request::METHOD_GET, self::ROUTE_FR);
        $csrfToken = $crawler->filter('input[name="routine[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, self::ROUTE_FR, [
            'routine' => [
                '_token' => $csrfToken,
                'name' => $name,
                'description' => $description,
                'exercises' => $exercisesJson,
            ],
        ]);
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

    private function findRoutine(string $name): ?Routine
    {
        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $user = $userRepo->findOneBy([
            'email' => self::OWNER,
        ]);
        self::assertNotNull($user);

        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);

        return $repo->findOneBy([
            'name' => $name,
            'owner' => $user,
        ]);
    }
}
