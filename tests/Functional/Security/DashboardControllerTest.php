<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardControllerTest extends WebTestCase
{
    private const string USER_WITH_NO_DATA = 'user-fixture-0@test.com';

    public function testDashboardIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::USER_WITH_NO_DATA);
        $crawler = $client->request('GET', '/fr/tableau-de-bord');
        $this->assertResponseIsSuccessful();
        $this->assertSame('Tableau de bord | FitTracker', $crawler->filter('title')->text(), 'Le sélecteur title ne contient pas le texte attendu');
    }

    public function testDashboardIsNotAccessibleWhenNotLogged(): void
    {
        $client = static::createClient();
        $client->request('GET', '/fr/tableau-de-bord');
        $this->assertResponseRedirects('/fr/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Connexion', 'Le sélecteur H2 ne contient pas le texte attendu');
    }

    private function login(string $userEmail): KernelBrowser
    {
        $client = static::createClient();
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var User $testUser */
        $testUser = $userRepository->findOneBy([
            'email' => $userEmail,
        ]);

        $client->loginUser($testUser);

        return $client;
    }
}
