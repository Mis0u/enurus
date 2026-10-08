<?php

declare(strict_types=1);

namespace App\Tests\Functional\Seo;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SeoMetadataTest extends WebTestCase
{
    use FunctionalTestTrait;

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function localizedPageProvider(): iterable
    {
        yield 'english login page' => ['/en/', 'en'];
        yield 'german sign-up page' => ['/de/registrierung', 'de'];
        yield 'polish terms page' => ['/pl/regulamin', 'pl'];
    }

    #[DataProvider('localizedPageProvider')]
    public function testHtmlLangFollowsThePageLocale(string $url, string $expectedLocale): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSame($expectedLocale, $crawler->filter('html')->attr('lang'));
    }

    public function testNotFoundPageLangFollowsTheResolvedLocale(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/de/seite-gibt-es-nicht');

        self::assertResponseStatusCodeSame(404);
        self::assertSame('de', $crawler->filter('html')->attr('lang'));
    }

    public function testDescriptionIsTranslatedInThePageLocale(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/fr/');

        $description = $crawler->filter('meta[name="description"]')->attr('content');
        self::assertIsString($description);
        self::assertStringContainsString('carnet de musculation', $description);
        self::assertSame($description, $crawler->filter('meta[property="og:description"]')->attr('content'));
    }

    public function testLinkPreviewCarriesAnAbsoluteImageUrl(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/en/');

        self::assertSame('Enurus', $crawler->filter('meta[property="og:site_name"]')->attr('content'));
        self::assertStringStartsWith('http', (string) $crawler->filter('meta[property="og:image"]')->attr('content'));
    }

    public function testDescriptionSurvivesAPageThatOverridesTheMetaBlock(): void
    {
        $client = $this->login('user-fixture-11-workout@test.com');
        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('meta[name="turbo-cache-control"]'));
        self::assertCount(1, $crawler->filter('meta[name="description"]'));
    }
}
