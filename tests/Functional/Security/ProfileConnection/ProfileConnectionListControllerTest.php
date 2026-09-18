<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProfileConnectionListControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::LIST_URL);
    }

    public function testPageIsAccessibleAndShowsTheSearchForm(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::ACTOR, self::LIST_URL, 'Mes connexions | Enurus');
        self::assertSelectorExists('form[action$="/connexions/demander"] input[name="profile_connection_request[query]"]');
        self::assertSelectorTextContains('main', "Tu n'as pas encore de connexion.");
    }

    public function testEachKindOfConnectionIsListedInItsOwnSectionWithItsActions(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $sender = $this->getUserByEmail(self::OTHER);
        $target = $this->getUserByEmail(self::THIRD);
        $friend = $this->getUserByEmail(self::FOURTH);
        $received = $this->createConnection($sender, $actor);
        $sent = $this->createConnection($actor, $target);
        $accepted = $this->createConnection($friend, $actor, ProfileConnectionStatusEnum::ACCEPTED);

        $crawler = $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf('form[action$="/connexions/%s/accepter"]', $received->id));
        self::assertSelectorExists(\sprintf('form[action$="/connexions/%s/refuser"]', $received->id));
        self::assertSelectorExists(\sprintf('form[action$="/connexions/%s/annuler"]', $sent->id));
        self::assertSelectorExists(\sprintf('form[action$="/connexions/%s/retirer"]', $accepted->id));
        self::assertSelectorExists(\sprintf('a[href$="/connexions/%s/tableau-de-bord"]', $accepted->id));
        self::assertStringContainsString($sender->nickname, $crawler->filter('#profile-connection-received-title + ul')->text());
        self::assertStringContainsString($target->nickname, $crawler->filter('#profile-connection-sent-title + ul')->text());
        self::assertStringContainsString($friend->nickname, $crawler->filter('#profile-connection-connections-title + ul')->text());
    }

    public function testEndedConnectionsAreNotListed(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->createConnection($this->getUserByEmail(self::OTHER), $actor, ProfileConnectionStatusEnum::DECLINED);
        $this->createConnection($actor, $this->getUserByEmail(self::THIRD), ProfileConnectionStatusEnum::REVOKED);

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#profile-connection-received-title');
        self::assertSelectorNotExists('#profile-connection-sent-title');
        self::assertSelectorTextContains('main', "Tu n'as pas encore de connexion.");
    }

    public function testNavigationBadgeCountsTheRequestsWaitingForAnAnswer(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->createConnection($this->getUserByEmail(self::OTHER), $actor);
        $this->createConnection($this->getUserByEmail(self::THIRD), $actor);
        $this->createConnection($actor, $this->getUserByEmail(self::FOURTH));

        $client->request('GET', '/fr/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.nav-badge', '2');
    }

    public function testNavigationHasNoBadgeWithoutPendingRequest(): void
    {
        $client = $this->login(self::ACTOR);

        $client->request('GET', '/fr/tableau-de-bord');

        self::assertSelectorExists('a[href$="/connexions"]');
        self::assertSelectorNotExists('a[href$="/connexions"] .nav-badge');
    }

    public function testListIsAlsoAvailableInEnglish(): void
    {
        $client = $this->login(self::ACTOR);

        $client->request('GET', '/en/connections');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Add a connection');
    }
}
