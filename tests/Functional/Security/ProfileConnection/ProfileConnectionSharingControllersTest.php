<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProfileConnectionSharingControllersTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    private const string TOGGLE_URL = '/fr/connexions/partage';

    private const string REGENERATE_URL = '/fr/connexions/partage/regenerer';

    public function testEnablingSharingAssignsAShareCode(): void
    {
        $client = $this->login(self::ACTOR);
        $token = $this->toggleTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'isDiscoverable' => true,
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $user = $this->getUserByEmail(self::ACTOR);
        self::assertTrue($user->isDiscoverable);
        self::assertNotNull($user->shareCode);
    }

    public function testDisablingSharingKeepsThePreviousShareCode(): void
    {
        $client = $this->login(self::ACTOR);
        $this->makeDiscoverable($this->getUserByEmail(self::ACTOR), 'ABC123');
        $token = $this->toggleTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'isDiscoverable' => false,
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $user = $this->getUserByEmail(self::ACTOR);
        self::assertFalse($user->isDiscoverable);
        self::assertSame('ABC123', $user->shareCode);
    }

    public function testInvalidCsrfTokenIsRejectedOnToggle(): void
    {
        $client = $this->login(self::ACTOR);

        $client->request(
            Request::METHOD_PATCH,
            self::TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'isDiscoverable' => true,
                '_token' => 'invalid',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRegeneratingReplacesTheShareCode(): void
    {
        $client = $this->login(self::ACTOR);
        $this->makeDiscoverable($this->getUserByEmail(self::ACTOR), 'ABC123');
        $token = $this->regenerateTokenFor($client);

        $client->request(
            Request::METHOD_POST,
            self::REGENERATE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        $user = $this->getUserByEmail(self::ACTOR);
        self::assertNotSame('ABC123', $user->shareCode);
    }

    public function testRegeneratingWhileNotDiscoverableIsRejected(): void
    {
        $client = $this->login(self::ACTOR);
        $token = $this->regenerateTokenFor($client);

        $client->request(
            Request::METHOD_POST,
            self::REGENERATE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidCsrfTokenIsRejectedOnRegenerate(): void
    {
        $client = $this->login(self::ACTOR);

        $client->request(
            Request::METHOD_POST,
            self::REGENERATE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                '_token' => 'invalid',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    private function toggleTokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            self::LIST_URL,
            '[data-profile-connection--profile-sharing-toggle-token-value]',
            'data-profile-connection--profile-sharing-toggle-token-value',
        );
    }

    private function regenerateTokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            self::LIST_URL,
            '[data-profile-connection--profile-sharing-regenerate-token-value]',
            'data-profile-connection--profile-sharing-regenerate-token-value',
        );
    }
}
