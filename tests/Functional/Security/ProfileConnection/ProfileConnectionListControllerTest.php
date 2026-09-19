<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\User;
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

    public function testSharedWidgetsCardListsUnlockedWidgetsAllCheckedByDefault(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->makeDiscoverable($actor, 'LSTCK1');
        $actor->shareWorkouts = true;
        $this->entityManager()->flush();

        $crawler = $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="profile-connection--shared-widgets"]');
        $checkboxes = $crawler->filter('[data-controller="profile-connection--shared-widgets"] input[type="checkbox"]');
        self::assertGreaterThan(0, $checkboxes->count());
        $checkboxes->each(static function ($node): void {
            self::assertSame('', $node->attr('checked'));
            self::assertNull($node->attr('disabled'));
        });
    }

    public function testAWidgetHiddenForSharingIsRenderedUnchecked(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->makeDiscoverable($actor, 'LSTCK2');
        $actor->shareWorkouts = true;
        $actor->hiddenSharedWidgets = ['tonnage'];
        $this->entityManager()->flush();

        $crawler = $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        $checkbox = $crawler->filter('input[data-profile-connection--shared-widgets-widget-param="tonnage"]');
        self::assertNull($checkbox->attr('checked'));
    }

    public function testAWidgetHiddenOnOwnDashboardIsNotProposedForSharing(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $actor->hiddenWidgets = ['tonnage'];
        $this->entityManager()->flush();

        $crawler = $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('input[data-profile-connection--shared-widgets-widget-param="tonnage"]'));
    }

    public function testSharedWidgetsAreUncheckedAndDisabledWhenProfileSharingIsOff(): void
    {
        $client = $this->login(self::ACTOR);

        $crawler = $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        $checkboxes = $crawler->filter('[data-controller="profile-connection--shared-widgets"] input[type="checkbox"]');
        self::assertGreaterThan(0, $checkboxes->count());
        $checkboxes->each(static function ($node): void {
            self::assertNull($node->attr('checked'));
            self::assertSame('', $node->attr('disabled'));
        });
    }

    public function testSharedWidgetsAreUncheckedAndDisabledWhenWorkoutSharingIsOff(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->makeDiscoverable($actor, 'LSTCK3');
        $this->entityManager()->flush();

        $crawler = $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        $checkboxes = $crawler->filter('[data-controller="profile-connection--shared-widgets"] input[type="checkbox"]');
        self::assertGreaterThan(0, $checkboxes->count());
        $checkboxes->each(static function ($node): void {
            self::assertNull($node->attr('checked'));
            self::assertSame('', $node->attr('disabled'));
        });
    }

    public function testEachKindOfConnectionIsListedInItsOwnSectionWithItsActions(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $sender = $this->getUserByEmail(self::OTHER);
        $target = $this->getUserByEmail(self::THIRD);
        $friend = $this->getUserByEmail(self::FOURTH);
        $this->makeDiscoverable($friend, 'DDDDD1');
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

    public function testViewDashboardButtonIsHiddenWhenTheCounterpartStoppedSharing(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $friend = $this->getUserByEmail(self::FOURTH);
        $accepted = $this->createConnection($friend, $actor, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists(\sprintf('a[href$="/connexions/%s/tableau-de-bord"]', $accepted->id));
        self::assertSelectorTextContains('main', $friend->nickname . ' a désactivé le partage de son profil');
    }

    public function testViewDashboardLinkIsMarkedAsBlockedWhenTheViewerStoppedSharing(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $friend = $this->getUserByEmail(self::FOURTH);
        $this->makeDiscoverable($friend, 'DDDDD2');
        $accepted = $this->createConnection($friend, $actor, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf(
            'a[href$="/connexions/%s/tableau-de-bord"][data-profile-connection--dashboard-link-blocked-value="true"]',
            $accepted->id,
        ));
    }

    public function testViewDashboardLinkIsNotBlockedWhenTheViewerIsCurrentlySharing(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $friend = $this->getUserByEmail(self::FOURTH);
        $this->makeDiscoverable($actor, 'DDDDD3');
        $this->makeDiscoverable($friend, 'DDDDD4');
        $accepted = $this->createConnection($friend, $actor, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists(\sprintf(
            'a[href$="/connexions/%s/tableau-de-bord"][data-profile-connection--dashboard-link-blocked-value="false"]',
            $accepted->id,
        ));
    }

    public function testConnectionsListIsNotScrollableBelowFiveEntries(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->createConnection($this->getUserByEmail(self::OTHER), $actor, ProfileConnectionStatusEnum::ACCEPTED);
        $this->createConnection($this->getUserByEmail(self::THIRD), $actor, ProfileConnectionStatusEnum::ACCEPTED);
        $this->createConnection($this->getUserByEmail(self::FOURTH), $actor, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#profile-connection-connections-title + ul.exercise-list-scroll');
    }

    public function testConnectionsListBecomesScrollableFromFiveEntries(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->createConnection($this->getUserByEmail(self::OTHER), $actor, ProfileConnectionStatusEnum::ACCEPTED);
        $this->createConnection($this->getUserByEmail(self::THIRD), $actor, ProfileConnectionStatusEnum::ACCEPTED);
        $this->createConnection($this->getUserByEmail(self::FOURTH), $actor, ProfileConnectionStatusEnum::ACCEPTED);
        $this->createConnection($this->createExtraUser('extra1@test.com'), $actor, ProfileConnectionStatusEnum::ACCEPTED);
        $this->createConnection($this->createExtraUser('extra2@test.com'), $actor, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#profile-connection-connections-title + ul.exercise-list-scroll');
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

    private function createExtraUser(string $email): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = explode('@', $email)[0];
        $user->lastLogin = new \DateTimeImmutable();
        $user->isVerified = true;

        $this->entityManager()->persist($user);
        $this->entityManager()->flush();

        return $user;
    }
}
