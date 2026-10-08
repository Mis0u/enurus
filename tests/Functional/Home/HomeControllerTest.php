<?php

declare(strict_types=1);

namespace App\Tests\Functional\Home;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class HomeControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    public function testVisitorSeesWhatTheSiteIs(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'carnet de musculation');
        self::assertSelectorTextContains('body', 'Gratuit et sans publicité');
    }

    public function testVisitorCanReachSignUpAndLogin(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/de/');

        self::assertGreaterThan(0, $crawler->filter('a[href="/de/registrierung"]')->count());
        self::assertGreaterThan(0, $crawler->filter('a[href="/de/anmelden"]')->count());
    }

    public function testLoggedUserGoesStraightToTheDashboard(): void
    {
        $client = $this->login('user-fixture-11-workout@test.com');
        $client->request(Request::METHOD_GET, '/fr/');

        self::assertResponseRedirects('/fr/tableau-de-bord');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function localizedLoginPathProvider(): iterable
    {
        yield 'en' => ['/en/login'];
        yield 'fr' => ['/fr/connexion'];
        yield 'it' => ['/it/accesso'];
        yield 'es' => ['/es/iniciar-sesion'];
        yield 'pt' => ['/pt/entrar'];
        yield 'de' => ['/de/anmelden'];
        yield 'nl' => ['/nl/inloggen'];
        yield 'pl' => ['/pl/logowanie'];
    }

    #[DataProvider('localizedLoginPathProvider')]
    public function testLoginFormIsServedOnItsLocalizedPath(string $loginPath): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, $loginPath);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_password"]');
    }
}
