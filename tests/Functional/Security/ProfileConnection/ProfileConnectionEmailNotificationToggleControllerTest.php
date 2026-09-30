<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProfileConnectionEmailNotificationToggleControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    private const string TOGGLE_URL = '/fr/connexions/notifications';

    private const string TOKEN_ATTRIBUTE = 'data-email-notification-toggle-csrf-token-value';

    public function testRequestEmailsAreOnByDefault(): void
    {
        $client = $this->login(self::ACTOR);

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#notifications input[type="checkbox"][checked]'));
    }

    public function testTurningRequestEmailsOffIsSaved(): void
    {
        $client = $this->login(self::ACTOR);

        $this->sendToggle($client, false, $this->tokenFor($client));

        self::assertResponseIsSuccessful();
        self::assertFalse($this->getUserByEmail(self::ACTOR)->emailOnConnectionRequest);
    }

    public function testTurningRequestEmailsBackOnIsSaved(): void
    {
        $client = $this->login(self::ACTOR);
        $this->getUserByEmail(self::ACTOR)->emailOnConnectionRequest = false;
        $this->entityManager()->flush();

        $this->sendToggle($client, true, $this->tokenFor($client));

        self::assertResponseIsSuccessful();
        self::assertTrue($this->getUserByEmail(self::ACTOR)->emailOnConnectionRequest);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::ACTOR);

        $this->sendToggle($client, false, 'invalid');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertTrue($this->getUserByEmail(self::ACTOR)->emailOnConnectionRequest);
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
        return $this->csrfTokenFromPage($client, self::LIST_URL, '[' . self::TOKEN_ATTRIBUTE . ']', self::TOKEN_ATTRIBUTE);
    }
}
