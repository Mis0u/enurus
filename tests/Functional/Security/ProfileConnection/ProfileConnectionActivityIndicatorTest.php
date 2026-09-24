<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\ProfileConnection;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Point « nouvelle séance » sur une connexion : OTHER (fixture à 26 séances) a des séances créées
 * au chargement des fixtures — une dernière visite très ancienne les rend toutes nouvelles, une
 * visite future aucune.
 */
final class ProfileConnectionActivityIndicatorTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    private const string INDICATOR = '[data-new-workout-indicator]';

    public function testIndicatorIsShownWhenTheConnectionCreatedAWorkoutSinceTheLastVisit(): void
    {
        $client = $this->login(self::ACTOR);
        $this->createSharedConnectionLastSeenByActor('-10 years');

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#profile-connection-connections-title + ul ' . self::INDICATOR);
    }

    public function testIndicatorIsHiddenWithoutWorkoutSinceTheLastVisit(): void
    {
        $client = $this->login(self::ACTOR);
        $this->createSharedConnectionLastSeenByActor('+1 hour');

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::INDICATOR);
    }

    public function testIndicatorIsHiddenWhenTheViewerStoppedSharing(): void
    {
        $client = $this->login(self::ACTOR);
        $connection = $this->createSharedConnectionLastSeenByActor('-10 years');
        $connection->requester->isDiscoverable = false;
        $this->entityManager()->flush();

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(self::INDICATOR);
    }

    public function testVisitingTheConnectionDashboardClearsTheIndicator(): void
    {
        $client = $this->login(self::ACTOR);
        $connection = $this->createSharedConnectionLastSeenByActor('-10 years');

        $client->request('GET', \sprintf('/fr/connexions/%s/tableau-de-bord', $connection->id));
        self::assertResponseIsSuccessful();
        $client->request('GET', self::LIST_URL);

        self::assertSelectorNotExists(self::INDICATOR);
    }

    public function testVisitingTheConnectionWorkoutListClearsTheIndicator(): void
    {
        $client = $this->login(self::ACTOR);
        $connection = $this->createSharedConnectionLastSeenByActor('-10 years');
        $connection->addressee->shareWorkouts = true;
        $this->entityManager()->flush();

        $client->request('GET', \sprintf('/fr/connexions/%s/seances', $connection->id));
        self::assertResponseIsSuccessful();
        $client->request('GET', self::LIST_URL);

        self::assertSelectorNotExists(self::INDICATOR);
    }

    public function testVisitingTheDashboardOfTheOtherPartyDoesNotClearItsOwnIndicator(): void
    {
        $client = $this->login(self::ACTOR);
        $connection = $this->createSharedConnectionLastSeenByActor('-10 years');

        $client->request('GET', \sprintf('/fr/connexions/%s/tableau-de-bord', $connection->id));

        self::assertNull($this->findConnection($connection)?->lastSeenBy($this->getUserByEmail(self::OTHER)));
    }

    private function createSharedConnectionLastSeenByActor(string $lastVisit): ProfileConnection
    {
        $actor = $this->getUserByEmail(self::ACTOR);
        $other = $this->getUserByEmail(self::OTHER);
        $this->makeDiscoverable($actor, 'ACT0R1');
        $this->makeDiscoverable($other, 'HUBCDE');

        $connection = $this->createConnection($actor, $other, ProfileConnectionStatusEnum::ACCEPTED);
        $connection->markSeenBy($actor, new \DateTimeImmutable($lastVisit));
        $this->entityManager()->flush();

        return $connection;
    }
}
