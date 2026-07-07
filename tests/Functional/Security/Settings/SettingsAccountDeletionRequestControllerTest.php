<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsAccountDeletionRequestControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use MailerAssertionsTrait;

    private const string USER = 'user-fixture-2@test.com';

    private const string URL = '/fr/reglages/compte/suppression';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_POST, self::URL);

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testRequestSucceedsAndPersistsDeletionRequestedAt(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $token = $crawler->filter('[data-settings--account-deletion-csrf-token-value]')->first()->attr('data-settings--account-deletion-csrf-token-value');

        $client->request(Request::METHOD_POST, self::URL, [
            '_token' => $token,
        ]);

        $this->assertResponseIsSuccessful();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        self::assertNotNull($user->deletionRequestedAt);
    }

    public function testRequestReturnsLogoutUrl(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $token = $crawler->filter('[data-settings--account-deletion-csrf-token-value]')->first()->attr('data-settings--account-deletion-csrf-token-value');

        $client->request(Request::METHOD_POST, self::URL, [
            '_token' => $token,
        ]);

        $data = json_decode((string) $client->getResponse()->getContent(), true);

        self::assertIsArray($data);
        self::assertArrayHasKey('logoutUrl', $data);
    }

    public function testRequestSendsConfirmationEmail(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $token = $crawler->filter('[data-settings--account-deletion-csrf-token-value]')->first()->attr('data-settings--account-deletion-csrf-token-value');

        $client->request(Request::METHOD_POST, self::URL, [
            '_token' => $token,
        ]);

        $this->assertQueuedEmailCount(1);
    }

    public function testRequestFailsWithInvalidCsrfToken(): void
    {
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_POST, self::URL, [
            '_token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(403);
    }
}
