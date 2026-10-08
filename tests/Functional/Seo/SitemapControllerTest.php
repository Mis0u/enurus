<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

final class SitemapControllerTest extends WebTestCase
{
    public function testSitemapListsTranslatedPagesInEveryLanguage(): void
    {
        $sitemap = $this->requestSitemap();

        self::assertCount(17, $sitemap->filterXPath('//s:url'));
        self::assertSame(1, $this->countLocations($sitemap, 'http://localhost/de/'));
        self::assertSame(1, $this->countLocations($sitemap, 'http://localhost/pl/rejestracja'));
    }

    public function testSitemapListsTermsOnlyInFrench(): void
    {
        $sitemap = $this->requestSitemap();

        self::assertSame(1, $this->countLocations($sitemap, 'http://localhost/fr/cgu'));
        self::assertSame(0, $this->countLocations($sitemap, 'http://localhost/pl/regulamin'));
    }

    public function testSitemapLeavesOutTheLoginPage(): void
    {
        $sitemap = $this->requestSitemap();

        self::assertSame(0, $this->countLocations($sitemap, 'http://localhost/fr/connexion'));
    }

    public function testEachEntryPointsToItsOtherLanguageVersions(): void
    {
        $sitemap = $this->requestSitemap();

        $firstEntry = $sitemap->filterXPath('//s:url')->first();
        self::assertCount(9, $firstEntry->filterXPath('.//xhtml:link'));
    }

    public function testSitemapCanBeCachedPublicly(): void
    {
        $this->requestSitemap();

        self::assertResponseHeaderSame('Cache-Control', 'max-age=86400, public');
    }

    /**
     * En prod, Sentry lit l'utilisateur connecté sur chaque requête non `stateless` : la session est
     * consultée et Symfony rend la réponse privée, ce qui annule le cache public du sitemap. Sentry
     * n'étant pas actif en test, c'est l'option de route elle-même qui est vérifiée.
     */
    public function testSitemapRouteNeverTouchesTheSession(): void
    {
        self::bootKernel();
        /** @var RouterInterface $router */
        $router = static::getContainer()->get(RouterInterface::class);

        self::assertTrue($router->getRouteCollection()->get('app_sitemap')?->getDefault('_stateless'));
    }

    private function requestSitemap(): Crawler
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/sitemap.xml');

        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');

        $crawler = new Crawler((string) $client->getResponse()->getContent());
        $crawler->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $crawler->registerNamespace('xhtml', 'http://www.w3.org/1999/xhtml');

        return $crawler;
    }

    private function countLocations(Crawler $sitemap, string $url): int
    {
        return $sitemap->filterXPath('//s:loc')->reduce(static fn (Crawler $loc): bool => $loc->text() === $url)->count();
    }
}
