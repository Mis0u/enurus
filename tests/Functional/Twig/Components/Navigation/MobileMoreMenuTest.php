<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig\Components\Navigation;

use App\DataFixtures\UserFixtures;
use App\Tests\Functional\Security\ProfileConnection\ProfileConnectionTestTrait;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Bouton « Plus » de la barre de navigation mobile et son panneau : toutes les pages absentes de
 * la barre du bas, avec leurs compteurs.
 */
final class MobileMoreMenuTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    private const string MORE_BUTTON = 'button[aria-controls="mobile-more-menu"]';

    public function testTheBottomBarLeadsToTheLibraryAndNoLongerToTheSettings(): void
    {
        $crawler = $this->pageFor(self::ACTOR, '/fr/tableau-de-bord');
        $barLink = static fn (string $href): Crawler => $crawler->filter(\sprintf('nav[aria-label="Navigation principale"] > a[href="%s"]', $href));

        self::assertCount(1, $barLink('/fr/bibliotheque'));
        self::assertCount(0, $barLink('/fr/reglages'));
        self::assertCount(0, $barLink('/fr/messagerie'));
    }

    public function testThePanelListsEveryPageMissingFromTheBottomBar(): void
    {
        $panel = $this->panel($this->pageFor(self::ACTOR, '/fr/tableau-de-bord'));

        foreach (['/fr/mes-routines', '/fr/connexions', '/fr/mes-badges', '/fr/messagerie', '/fr/contact', '/fr/reglages', '/fr/logout'] as $href) {
            self::assertCount(1, $panel->filter(\sprintf('a[href="%s"]', $href)), $href);
        }
    }

    public function testAnAdminGetsTheAdministrationInsteadOfTheMessaging(): void
    {
        $panel = $this->panel($this->pageFor(UserFixtures::USER_ADMIN, '/fr/tableau-de-bord'));

        self::assertCount(1, $panel->filter('a[href="/admin"]'));
        self::assertCount(0, $panel->filter('a[href="/fr/messagerie"]'));
        self::assertCount(0, $panel->filter('a[href="/fr/contact"]'));
    }

    public function testTheMoreButtonIsHighlightedOnAPageOfThePanel(): void
    {
        $crawler = $this->pageFor(self::ACTOR, '/fr/mes-routines');

        self::assertSame('true', $crawler->filter(self::MORE_BUTTON)->attr('data-active'));
    }

    public function testTheMoreButtonIsNotHighlightedOnAPageOfTheBottomBar(): void
    {
        $crawler = $this->pageFor(self::ACTOR, '/fr/tableau-de-bord');

        self::assertSame('false', $crawler->filter(self::MORE_BUTTON)->attr('data-active'));
    }

    public function testPendingRequestsAreCountedOnTheirTileAndFlaggedOnTheMoreButton(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->createConnection($this->getUserByEmail(self::OTHER), $actor);
        $this->createConnection($this->getUserByEmail(self::THIRD), $actor);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSame('2', trim($this->panel($crawler)->filter('a[href="/fr/connexions"] .tile-badge')->text()));
        self::assertCount(1, $crawler->filter(self::MORE_BUTTON . ' .more-notification-dot'));
    }

    public function testTheMenuButtonOfTheTopBarIsGone(): void
    {
        $crawler = $this->pageFor(self::ACTOR, '/fr/tableau-de-bord');

        self::assertCount(0, $crawler->filter('button[aria-label="Ouvrir le menu"]'));
    }

    private function pageFor(string $email, string $url): Crawler
    {
        $client = $this->login($email);
        $crawler = $client->request(Request::METHOD_GET, $url);
        self::assertResponseIsSuccessful();

        return $crawler;
    }

    private function panel(Crawler $crawler): Crawler
    {
        return $crawler->filter('dialog#mobile-more-menu');
    }
}
