<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SettingsAdminMessageEmailControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string ADMIN = 'admin-fixture@test.com';

    private const string SETTINGS_URL = '/fr/reglages';

    private const string TOGGLE_URL = '/fr/reglages/email-messages';

    private const string TOKEN_ATTRIBUTE = 'data-email-notification-toggle-csrf-token-value';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_PATCH, self::TOGGLE_URL);

        self::assertContains($client->getResponse()->getStatusCode(), [Response::HTTP_FOUND, Response::HTTP_UNAUTHORIZED]);
    }

    public function testEmailsAreOnByDefault(): void
    {
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, self::SETTINGS_URL);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#notifications input[type="checkbox"][checked]'));
    }

    public function testCardNamesTheBrandInItsExplanation(): void
    {
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, self::SETTINGS_URL);

        self::assertStringContainsString("de la part d'Enurus.", $crawler->filter('#notifications')->text());
    }

    public function testAdminDoesNotSeeTheCard(): void
    {
        $client = $this->login(self::ADMIN);

        $crawler = $client->request(Request::METHOD_GET, self::SETTINGS_URL);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#notifications'));
    }

    public function testAdminCannotChangeTheSetting(): void
    {
        $client = $this->login(self::ADMIN);

        $this->sendToggle($client, false, 'any-token');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        self::assertTrue($this->getUserByEmail(self::ADMIN)->emailOnAdminMessage);
    }

    public function testTurningEmailsOffIsSaved(): void
    {
        $client = $this->login(self::USER);

        $this->sendToggle($client, false, $this->tokenFor($client));

        self::assertResponseIsSuccessful();
        self::assertFalse($this->getUserByEmail(self::USER)->emailOnAdminMessage);
    }

    public function testTurningEmailsBackOnIsSaved(): void
    {
        $client = $this->login(self::USER);
        $this->getUserByEmail(self::USER)->emailOnAdminMessage = false;
        $this->entityManager()->flush();

        $this->sendToggle($client, true, $this->tokenFor($client));

        self::assertResponseIsSuccessful();
        self::assertTrue($this->getUserByEmail(self::USER)->emailOnAdminMessage);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER);

        $this->sendToggle($client, false, 'invalid');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertTrue($this->getUserByEmail(self::USER)->emailOnAdminMessage);
    }

    private function sendToggle(KernelBrowser $client, bool $enabled, string $token): void
    {
        $client->request(
            Request::METHOD_PATCH,
            self::TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'enabled' => $enabled,
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );
    }

    private function tokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage($client, self::SETTINGS_URL, '#notifications', self::TOKEN_ATTRIBUTE);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
