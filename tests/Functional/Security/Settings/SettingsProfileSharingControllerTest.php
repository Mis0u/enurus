<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SettingsProfileSharingControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string URL = '/fr/reglages/partage-de-profil';

    public function testEnablingSharingAssignsAShareCode(): void
    {
        $client = $this->login(self::USER);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'toggle',
                'enabled' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $user = $this->getUserByEmail(self::USER);
        self::assertTrue($user->isDiscoverable);
        self::assertNotNull($user->shareCode);
    }

    public function testDisablingSharingKeepsThePreviousShareCode(): void
    {
        $client = $this->login(self::USER);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'toggle',
                'enabled' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );
        $shareCode = $this->getUserByEmail(self::USER)->shareCode;

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'toggle',
                'enabled' => false,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $user = $this->getUserByEmail(self::USER);
        self::assertFalse($user->isDiscoverable);
        self::assertSame($shareCode, $user->shareCode);
    }

    public function testRegeneratingReplacesTheShareCode(): void
    {
        $client = $this->login(self::USER);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'toggle',
                'enabled' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );
        $previousShareCode = $this->getUserByEmail(self::USER)->shareCode;

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'regenerate',
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $user = $this->getUserByEmail(self::USER);
        self::assertNotSame($previousShareCode, $user->shareCode);
    }

    public function testRegeneratingWhileNotDiscoverableIsRejected(): void
    {
        $client = $this->login(self::USER);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'regenerate',
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidActionIsRejected(): void
    {
        $client = $this->login(self::USER);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'not_a_real_action',
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'action' => 'toggle',
                'enabled' => true,
                '_token' => 'invalid',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function csrfTokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            '/fr/reglages',
            '[data-settings--profile-sharing-csrf-token-value]',
            'data-settings--profile-sharing-csrf-token-value',
        );
    }
}
