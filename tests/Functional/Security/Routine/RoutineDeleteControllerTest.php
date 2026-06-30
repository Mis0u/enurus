<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Routine;

use App\DataFixtures\UserFixtures;
use App\Entity\Routine;
use App\Repository\RoutineExerciseRepository;
use App\Repository\RoutineRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoutineDeleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string OTHER_USER = UserFixtures::USER_ROUTINE_OTHER;

    // -------------------------------------------------------------------------
    // ACCÈS
    // -------------------------------------------------------------------------

    public function testIsRejectedWithoutXhrHeader(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();

        $client->request(Request::METHOD_DELETE, $this->getDeleteUrl($routine));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();

        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);
        $routine = $repo->findOneBy([
            'name' => 'Push Day',
        ]);
        self::assertNotNull($routine);

        $client->request(
            Request::METHOD_DELETE,
            $this->getDeleteUrl($routine),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseRedirects();
    }

    public function testIsForbiddenForOtherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $routine = $this->getOwnerRoutine();

        $client->request(
            Request::METHOD_DELETE,
            $this->getDeleteUrl($routine),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // SUPPRESSION — cas valides
    // -------------------------------------------------------------------------

    public function testRoutineIsDeletedFromDatabase(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $routineId = $routine->id;

        $this->sendDeleteRequest($client, $routine);

        self::assertResponseIsSuccessful();

        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);
        self::assertNull($repo->find($routineId));
    }

    public function testRoutineExercisesAreDeletedInCascade(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $routineId = $routine->id;

        $this->sendDeleteRequest($client, $routine);

        self::assertResponseIsSuccessful();

        /** @var RoutineExerciseRepository $repo */
        $repo = static::getContainer()->get(RoutineExerciseRepository::class);
        $remaining = $repo->findBy([
            'routine' => $routineId,
        ]);

        self::assertCount(0, $remaining);
    }

    public function testResponseContainsSuccessTrue(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();

        $this->sendDeleteRequest($client, $routine);

        self::assertResponseIsSuccessful();

        $content = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertTrue($content['success']);
    }

    // -------------------------------------------------------------------------
    // ISOLATION
    // -------------------------------------------------------------------------

    public function testOtherUserCannotDeleteRoutine(): void
    {
        $client = $this->login(self::OTHER_USER);
        $routine = $this->getOwnerRoutine();
        $routineId = $routine->id;

        $client->request(
            Request::METHOD_DELETE,
            $this->getDeleteUrl($routine),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);
        self::assertNotNull($repo->find($routineId));
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    private function sendDeleteRequest(KernelBrowser $client, Routine $routine): void
    {
        $client->request(
            Request::METHOD_DELETE,
            $this->getDeleteUrl($routine),
            server: [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );
    }

    private function getDeleteUrl(Routine $routine): string
    {
        self::assertNotNull($routine->id);
        return '/fr/mes-routines/' . $routine->id->toRfc4122() . '/supprimer';
    }

    private function getOwnerRoutine(): Routine
    {
        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $owner = $userRepo->findOneBy([
            'email' => self::OWNER,
        ]);
        self::assertNotNull($owner);

        /** @var RoutineRepository $repo */
        $repo = static::getContainer()->get(RoutineRepository::class);
        $routine = $repo->findOneBy([
            'owner' => $owner,
            'name' => 'Push Day',
        ]);
        self::assertNotNull($routine);

        return $routine;
    }
}
