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
        $client->loginUser($this->getUserByEmail($userEmail));

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

    private function getUserByEmail(string $email): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        if (! $user instanceof User) {
            throw new \LogicException(\sprintf('Fixture user "%s" not found.', $email));
        }

        return $user;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    private function deleteRequest(KernelBrowser $client, string $url): void
    {
        $client->request(
            Request::METHOD_DELETE,
            $url,
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );
    }
}
