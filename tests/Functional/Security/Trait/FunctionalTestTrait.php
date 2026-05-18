<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Trait;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

trait FunctionalTestTrait
{
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

    private function assertPageIsAccessibleWhenLogged(string $email, string $url, string $titlePage, ?KernelBrowser $client = null): void
    {
        $client = $client ?? $this->login($email);
        $crawler = $client->request(Request::METHOD_GET, $url);
        $this->assertResponseIsSuccessful();
        $this->assertSame($titlePage, $crawler->filter('title')->text(), 'Le sélecteur title ne contient pas le texte attendu');
    }

    private function assertPageIsRedirectToLoginWhenNotLogged(string $url, ?KernelBrowser $client = null): void
    {
        $client = $client ?? static::createClient();
        $client->request(Request::METHOD_GET, $url);
        $this->assertResponseRedirects('/fr/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Connexion', 'Le sélecteur H2 ne contient pas le texte attendu');
    }
}
