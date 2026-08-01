<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\Cookie;
use function Symfony\Component\Clock\now;
use Symfony\Component\HttpFoundation\Request;

class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            'title',
            'Enurus',
            'Le titre de la page ne contient pas le texte demandé'
        );
    }

    public function testLoginFormContainsRequiredFields(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/');
        $this->selectorExists();
    }

    public function testLoginWithInvalidCredentialsShowsError(): void
    {
        $client = $this->getCredentials('toto@test.com', 'Symfony_rocks!');

        $this->assertResponseRedirects('/fr/');

        $client->followRedirect();

        $this->assertSelectorTextContains(
            '.error-msg',
            'Identifiants invalides.',
            'il n\'y a pas le texte d\'erreur'
        );
    }

    /**
     * `BlockedUserChecker` (`checkPreAuth`) doit rejeter la connexion d'un compte bloqué avec un
     * message dédié, même si les identifiants sont corrects — au lieu du générique "Identifiants
     * invalides." déjà couvert par testLoginWithInvalidCredentialsShowsError().
     */
    public function testLoginWithBlockedAccountShowsBlockedMessage(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => 'user-fixture-0@test.com',
        ]);
        $user->accountBlockedAt = new \DateTimeImmutable();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->flush();

        $crawler = $client->request(Request::METHOD_GET, '/fr/');
        $buttonCrawlerNode = $crawler->selectButton('Se connecter');
        $form = $buttonCrawlerNode->form();

        $client->submit($form, [
            '_username' => 'user-fixture-0@test.com',
            '_password' => 'pass_1234',
            '_remember_me' => false,
        ]);

        $this->assertResponseRedirects('/fr/');
        $client->followRedirect();

        $this->assertSelectorTextContains('.error-msg', 'Ton compte a été bloqué.');
    }

    public function testLoginWithValidCredentialsRedirectsToDashboard(): void
    {
        $client = $this->getCredentials('user-fixture-0@test.com', 'pass_1234');

        $this->assertResponseRedirects('/fr/tableau-de-bord');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('Tableau de bord | Enurus', $crawler->filter('title')->text(), 'Le sélecteur title ne contient pas le texte attendu');
    }

    public function testAlreadyAuthenticatedUserIsRedirectedFromLogin(): void
    {
        $client = $this->login();

        $client->request(Request::METHOD_GET, '/fr/');
        $this->assertResponseRedirects('/fr/tableau-de-bord');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('Tableau de bord | Enurus', $crawler->filter('title')->text(), 'Le sélecteur title ne contient pas le texte attendu');

    }

    public function testLogoutAndRedirects(): void
    {
        $client = $this->login();

        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        $this->assertSelectorTextContains('#logout', 'Déconnexion');
        $client->clickLink('Déconnexion');
        $this->assertResponseRedirects('/fr/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        $this->selectorExists();
    }

    public function testRememberMeCreatesPersistentCookie(): void
    {
        $cookie = $this->getCookieJar(true);
        $this->assertNotNull($cookie, 'Le cookie REMEMBERME doit être créé');
    }

    public function testWithoutRememberMeNoCookieIsCreated(): void
    {
        $cookie = $this->getCookieJar();

        $this->assertNull(
            $cookie,
            'Aucun cookie REMEMBERME ne doit être créé si checkbox non cochée'
        );
    }

    public function testRememberMeValidityCookie(): void
    {
        /** @var Cookie $cookie */
        $cookie = $this->getCookieJar(true);

        $now = time();
        $thirtyDaysInSeconds = 3600 * 24 * 30;
        $minimumExpiration = $now + $thirtyDaysInSeconds;
        $this->assertGreaterThanOrEqual(
            $minimumExpiration,
            $cookie->getExpiresTime(),
            'Le cookie doit expirer dans au moins 30 jours'
        );
    }

    public function testRememberMeClearsSessionAndRedirects(): void
    {
        $client = $this->getCredentials('user-fixture-0@test.com', 'pass_1234', true);

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $client->getCookieJar()->expire('PHPSESSID');

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        $this->assertResponseIsSuccessful();
        $this->assertSame('Tableau de bord | Enurus', $crawler->filter('title')->text());
    }

    public function testRememberMeCookieIsDeletedOnLogout(): void
    {
        $client = $this->getCredentials('user-fixture-0@test.com', 'pass_1234', true);

        $this->assertResponseRedirects('/fr/tableau-de-bord');
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSame('Tableau de bord | Enurus', $crawler->filter('title')->text());
        $client->clickLink('Déconnexion');

        $cookie = $client->getCookieJar()->get('REMEMBERME');
        $this->assertNull($cookie, 'Le cookie REMEMBERME doit être supprimé au logout');
    }

    public function testClickOnForgotPasswordShouldRedirect(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/');
        $client->clickLink('Mot de passe oublié ?');
        $this->assertRouteSame('app_forgot_password_request');

        $this->assertSelectorExists(
            'input#reset_password_request_form__token[type="hidden"]',
            'le champ caché n\'existe pas ou son type est incorrect'
        );
    }

    public function testClickOnRegistrationShouldRedirect(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/');
        $client->clickLink('Créer un compte');
        $this->assertRouteSame('app_register');

        $this->assertSelectorTextContains('button', 'Créer mon compte', 'Le sélecteur contenant \'Créer mon compte\' est introuvable');
    }

    private function getCredentials(string $username, string $password, bool $rememberMe = false): KernelBrowser
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/fr/');
        $buttonCrawlerNode = $crawler->selectButton('Se connecter');
        $form = $buttonCrawlerNode->form();

        $client->submit($form, [
            '_username' => $username,
            '_password' => $password,
            '_remember_me' => $rememberMe,
        ]);

        return $client;
    }

    private function selectorExists(): void
    {
        $this->assertSelectorExists(
            'input#username[type="email"]',
            'le champ email n\'existe pas ou son type est incorrect'
        );
        $this->assertSelectorExists(
            'input#password[type="password"]',
            'le champ password n\'existe pas ou son type est incorrect'
        );
        $this->assertSelectorExists(
            'Button[type="submit"]',
            'le bouton n\'existe pas ou son type est incorrect'
        );
    }

    private function getCookieJar(bool $rememberMe = false): ?Cookie
    {
        $client = $this->getCredentials('user-fixture-0@test.com', 'pass_1234', $rememberMe);

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $cookieJar = $client->getCookieJar();

        return $cookieJar->get('REMEMBERME');
    }

    private function login(): KernelBrowser
    {
        $client = static::createClient();
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var User $testUser */
        $testUser = $userRepository->findOneBy([
            'email' => 'user-fixture-0@test.com',
        ]);

        $client->loginUser($testUser);

        $now = now()->format('Y-m-d');
        $this->assertSame($testUser->lastLogin->format('Y-m-d'), $now);

        return $client;
    }
}
