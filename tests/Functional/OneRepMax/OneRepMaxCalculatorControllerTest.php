<?php

declare(strict_types=1);

namespace App\Tests\Functional\OneRepMax;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class OneRepMaxCalculatorControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string CALCULATOR_FR = '/fr/calculateur-1rm';

    private const string USER_IN_POUNDS = 'user-fixture-51-workout@test.com';

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function translatedAddressProvider(): iterable
    {
        yield 'english' => ['/en/one-rep-max-calculator', 'One-rep max calculator'];
        yield 'french' => [self::CALCULATOR_FR, 'Calculateur de 1RM'];
        yield 'german' => ['/de/1rm-rechner', '1RM-Rechner'];
        yield 'polish' => ['/pl/kalkulator-1rm', 'Kalkulator 1RM'];
    }

    #[DataProvider('translatedAddressProvider')]
    public function testVisitorReachesTheCalculatorInTheirLanguage(string $url, string $heading): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', $heading);
    }

    public function testVisitorCalculatesInKilosAndIsInvitedToSignUp(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::CALCULATOR_FR);

        self::assertSame('kg', $crawler->filter('[data-controller="one-rep-max--calculator"]')->attr('data-one-rep-max--calculator-unit-value'));
        self::assertCount(1, $crawler->filter('#one-rep-max-cta a[href="/fr/inscription"]'));
    }

    public function testMemberCalculatesInTheirOwnUnit(): void
    {
        $client = $this->login(self::USER_IN_POUNDS);
        $crawler = $client->request(Request::METHOD_GET, self::CALCULATOR_FR);

        self::assertSame('lbs', $crawler->filter('[data-controller="one-rep-max--calculator"]')->attr('data-one-rep-max--calculator-unit-value'));
        self::assertCount(1, $crawler->filter('#one-rep-max-cta a[href="/fr/tableau-de-bord"]'));
    }

    public function testCalculatorLinksToTheMainLiftGuides(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::CALCULATOR_FR);

        $guideLinks = $crawler->filter('#one-rep-max-guides + ul a');
        self::assertCount(4, $guideLinks);
        self::assertCount(1, $guideLinks->filter('a[href="/fr/exercices/developpe-couche-barre"]'));
    }

    public function testCalculatorExplainsTheOneRepMax(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/en/one-rep-max-calculator');

        self::assertCount(5, $crawler->filter('#one-rep-max-faq ~ div article h3'));
        self::assertSelectorTextContains('main', 'What about pull-ups or dips?');
    }

    public function testCalculatorPointsToItsOtherLanguageVersions(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::CALCULATOR_FR);

        self::assertSame('http://localhost/de/1rm-rechner', $crawler->filter('link[rel="alternate"][hreflang="de"]')->attr('href'));
        self::assertSame('http://localhost/fr/calculateur-1rm', $crawler->filter('link[rel="canonical"]')->attr('href'));
    }
}
