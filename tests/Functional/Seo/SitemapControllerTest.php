<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class SitemapControllerTest extends WebTestCase
{
    public function testSitemapListsEveryPublicPageInEveryLanguage(): void
    {
        $sitemap = $this->requestSitemap();

        self::assertCount(24, $sitemap->filterXPath('//s:url'));
        self::assertSame(1, $this->countLocations($sitemap, 'http://localhost/pl/regulamin'));
        self::assertSame(1, $this->countLocations($sitemap, 'http://localhost/de/'));
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
