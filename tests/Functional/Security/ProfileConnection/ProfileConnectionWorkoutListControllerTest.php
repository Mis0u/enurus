<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ProfileConnectionWorkoutListControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string VIEWER = 'user-fixture-11-workout@test.com';

    // 26 séances, en kg.
    private const string SUBJECT_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    // 51 séances, en lbs.
    private const string LBS_VIEWER = 'user-fixture-51-workout@test.com';

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $connection = $this->createSharedConnection(
            $this->getUserByEmail(self::VIEWER),
            $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS),
        );

        $this->assertPageIsRedirectToLoginWhenNotLogged($this->listUrl($connection), $client);
    }

    public function testUnknownConnectionIsNotFound(): void
    {
        $client = $this->login(self::VIEWER);

        $client->request('GET', '/fr/connexions/' . Uuid::v7()->toRfc4122() . '/seances');

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeniedWhenTheSubjectDoesNotShareWorkouts(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WLC001');
        $this->makeDiscoverable($subject, 'WLC002');
        // Le sujet partage son profil (dashboard visible) mais pas ses séances.
        $connection = $this->createSharedConnection($viewer, $subject);

        $client->request('GET', $this->listUrl($connection));

        self::assertResponseStatusCodeSame(403);
    }

    public function testViewerSeesTheSubjectsWorkouts(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WLC003');
        $this->makeWorkoutsShareable($subject, 'WLC004');
        $connection = $this->createSharedConnection($viewer, $subject);

        $client->request('GET', $this->listUrl($connection));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $subject->nickname);
        self::assertGreaterThan(0, $client->getCrawler()->filter('.show-back-btn, [class*="rounded-xl"]')->count());
    }

    public function testWeightUnitIsTheViewersNotTheOwners(): void
    {
        $client = $this->login(self::LBS_VIEWER);
        $viewer = $this->getUserByEmail(self::LBS_VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WLC005');
        $this->makeWorkoutsShareable($subject, 'WLC006');
        $connection = $this->createSharedConnection($viewer, $subject);

        $crawler = $client->request('GET', $this->listUrl($connection));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('lbs', $crawler->filter('body')->text());
    }

    public function testEditAndDeleteButtonsAreNeverShown(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WLC007');
        $this->makeWorkoutsShareable($subject, 'WLC008');
        $connection = $this->createSharedConnection($viewer, $subject);

        $client->request('GET', $this->listUrl($connection));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/modifier"]');
        self::assertSelectorNotExists('[data-controller="workout--list--delete-modal"]');
    }

    private function listUrl(ProfileConnection $connection): string
    {
        return '/fr/connexions/' . $connection->id?->toRfc4122() . '/seances';
    }

    private function makeDiscoverable(User $user, string $shareCode): void
    {
        $user->isDiscoverable = true;
        $user->shareCode = $shareCode;
        $this->entityManager()->flush();
    }

    private function makeWorkoutsShareable(User $user, string $shareCode): void
    {
        $this->makeDiscoverable($user, $shareCode);
        $user->shareWorkouts = true;
        $this->entityManager()->flush();
    }

    private function createSharedConnection(User $requester, User $addressee): ProfileConnection
    {
        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = ProfileConnectionStatusEnum::ACCEPTED;

        $this->entityManager()->persist($connection);
        $this->entityManager()->flush();

        return $connection;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
