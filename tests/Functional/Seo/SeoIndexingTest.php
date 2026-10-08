<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SeoIndexingTest extends WebTestCase
{
    public function testCanonicalUrlIgnoresQueryParameters(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription?invitation=abc');

        self::assertSame('http://localhost/fr/inscription', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertSame('http://localhost/fr/inscription', $crawler->filter('meta[property="og:url"]')->attr('content'));
    }

    public function testTermsInEveryLanguagePointToTheOnlyLegallyBindingFrenchVersion(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/de/agb');

        self::assertSame('http://localhost/fr/cgu', $crawler->filter('link[rel="canonical"]')->attr('href'));
        self::assertCount(0, $crawler->filter('link[rel="alternate"][hreflang]'));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function accountPageProvider(): iterable
    {
        yield 'login' => ['/fr/connexion'];
        yield 'password reset request' => ['/fr/reinitialiser-mot-de-passe'];
        yield 'password reset email check' => ['/fr/reinitialiser-mot-de-passe/verifier-email'];
    }

    #[DataProvider('accountPageProvider')]
    public function testAccountPagesStayOutOfSearchResults(string $url): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSame('noindex', $crawler->filter('meta[name="robots"]')->attr('content'));
    }

    public function testPagesMeantToBeFoundAreIndexable(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription');

        self::assertCount(0, $crawler->filter('meta[name="robots"]'));
    }

    public function testSignUpPageHasItsOwnTitleHeadingAndDescription(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription');

        self::assertSelectorTextSame('title', 'Inscription | Enurus');
        self::assertSelectorTextContains('h1', 'Créer un compte');
        self::assertStringContainsString('Crée ton compte', (string) $crawler->filter('meta[name="description"]')->attr('content'));
    }

    public function testLoginPageHasAMeaningfulTitleAndHeading(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/connexion');

        self::assertSelectorTextSame('title', 'Connexion | Enurus');
        self::assertSelectorTextContains('h1', 'Connexion');
    }

    public function testLinkPreviewDeclaresLocaleAndLargeCard(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/pt/');

        self::assertSame('pt_PT', $crawler->filter('meta[property="og:locale"]')->attr('content'));
        self::assertSame('summary_large_image', $crawler->filter('meta[name="twitter:card"]')->attr('content'));
    }
}
