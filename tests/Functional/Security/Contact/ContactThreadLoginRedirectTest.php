<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\Tests\Functional\Helper\ContactThreadTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Le bouton de l'email de message ouvre le fil : une personne déconnectée doit y arriver après
 * s'être connectée, sans que les autres pages changent de comportement.
 */
final class ContactThreadLoginRedirectTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = 'user-fixture-2@test.com';

    public function testLoginLeadsToTheThreadOpenedFromTheEmail(): void
    {
        $client = static::createClient();
        $thread = ContactThreadTestHelper::createThread($this->entityManager(), $this->getUserByEmail(self::OWNER));
        self::assertNotNull($thread->id);
        $threadUrl = $this->threadPath($client, $thread->id->toRfc4122());

        $client->request(Request::METHOD_GET, $threadUrl);
        $this->submitLoginForm($client);

        self::assertResponseRedirects();
        self::assertSame($threadUrl, $client->getResponse()->headers->get('Location'));
    }

    public function testLoginStillLeadsToTheDashboardFromAnyOtherPage(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, '/fr/mes-seances');
        $this->submitLoginForm($client);

        self::assertResponseRedirects();
        self::assertStringNotContainsString('mes-seances', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testAnExternalAddressIsNeverFollowed(): void
    {
        $client = static::createClient();
        $thread = ContactThreadTestHelper::createThread($this->entityManager(), $this->getUserByEmail(self::OWNER));
        self::assertNotNull($thread->id);
        $threadUrl = $this->threadPath($client, $thread->id->toRfc4122());

        $crawler = $client->request(Request::METHOD_GET, '/fr/connexion');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => self::OWNER,
            '_password' => 'pass_1234',
        ]);
        $client->request($form->getMethod(), $form->getUri(), [
            ...$form->getPhpValues(),
            '_target_path' => 'https://evil.example' . $threadUrl,
        ]);

        self::assertResponseRedirects();
        self::assertStringNotContainsString('evil.example', (string) $client->getResponse()->headers->get('Location'));
    }

    private function submitLoginForm(KernelBrowser $client): void
    {
        $crawler = $client->followRedirect();

        $client->submit($crawler->selectButton('Se connecter')->form([
            '_username' => self::OWNER,
            '_password' => 'pass_1234',
        ]));
    }

    private function threadPath(KernelBrowser $client, string $threadId): string
    {
        /** @var \Symfony\Component\Routing\RouterInterface $router */
        $router = $client->getContainer()->get('router');

        return $router->generate('app_contact_thread_show', [
            'id' => $threadId,
            '_locale' => 'fr',
        ]);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
