<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Entity\Workout;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ProfileConnectionWorkoutShowControllerTest extends WebTestCase
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
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $connection = $this->createSharedConnection($this->getUserByEmail(self::VIEWER), $subject);
        $workout = $this->oneWorkoutOf($subject);

        $this->assertPageIsRedirectToLoginWhenNotLogged($this->showUrl($connection, $workout), $client);
    }

    public function testUnknownConnectionIsNotFound(): void
    {
        $client = $this->login(self::VIEWER);
        $workout = $this->oneWorkoutOf($this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS));

        $client->request('GET', \sprintf('/fr/connexions/%s/seances/%s', Uuid::v7()->toRfc4122(), $workout->id?->toRfc4122()));

        self::assertResponseStatusCodeSame(404);
    }

    public function testDeniedWhenTheSubjectDoesNotShareWorkouts(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WSC001');
        $this->makeDiscoverable($subject, 'WSC002');
        $connection = $this->createSharedConnection($viewer, $subject);
        $workout = $this->oneWorkoutOf($subject);

        $client->request('GET', $this->showUrl($connection, $workout));

        self::assertResponseStatusCodeSame(403);
    }

    public function testShowsAWorkoutBelongingToTheSubject(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WSC003');
        $this->makeWorkoutsShareable($subject, 'WSC004');
        $connection = $this->createSharedConnection($viewer, $subject);
        $workout = $this->oneWorkoutOf($subject);

        $client->request('GET', $this->showUrl($connection, $workout));

        self::assertResponseIsSuccessful();
    }

    public function testAWorkoutBelongingToSomeoneElseIsNotFound(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WSC005');
        $this->makeWorkoutsShareable($subject, 'WSC006');
        $connection = $this->createSharedConnection($viewer, $subject);
        $someoneElsesWorkout = $this->oneWorkoutOf($viewer);

        $client->request('GET', $this->showUrl($connection, $someoneElsesWorkout));

        self::assertResponseStatusCodeSame(404);
    }

    public function testEditLinkIsNeverShown(): void
    {
        $client = $this->login(self::VIEWER);
        $viewer = $this->getUserByEmail(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WSC007');
        $this->makeWorkoutsShareable($subject, 'WSC008');
        $connection = $this->createSharedConnection($viewer, $subject);
        $workout = $this->oneWorkoutOf($subject);

        $client->request('GET', $this->showUrl($connection, $workout));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('a[href*="/modifier"]');
    }

    public function testWeightUnitFollowsTheViewerNotTheOwner(): void
    {
        $client = $this->login(self::LBS_VIEWER);
        $viewer = $this->getUserByEmail(self::LBS_VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->makeDiscoverable($viewer, 'WSC009');
        $this->makeWorkoutsShareable($subject, 'WSC010');
        $connection = $this->createSharedConnection($viewer, $subject);
        $workout = $this->oneWorkoutOf($subject);

        $crawler = $client->request('GET', $this->showUrl($connection, $workout));

        self::assertResponseIsSuccessful();
        self::assertStringContainsString('lbs', $crawler->filter('body')->text());
    }

    private function showUrl(ProfileConnection $connection, Workout $workout): string
    {
        return \sprintf('/fr/connexions/%s/seances/%s', $connection->id?->toRfc4122(), $workout->id?->toRfc4122());
    }

    private function oneWorkoutOf(User $owner): Workout
    {
        /** @var Workout|null $workout */
        $workout = $this->entityManager()->getRepository(Workout::class)->findOneBy([
            'owner' => $owner,
        ]);

        if (! $workout instanceof Workout) {
            throw new \LogicException(\sprintf('No workout found for "%s".', $owner->email));
        }

        return $workout;
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
