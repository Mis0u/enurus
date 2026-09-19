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

    private const string WORKOUT_TOGGLE_URL = '/fr/connexions/partage/seances';

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

    public function testEnablingWorkoutSharingWhileDiscoverableWorks(): void
    {
        $client = $this->login(self::ACTOR);
        $this->makeDiscoverable($this->getUserByEmail(self::ACTOR), 'WWWWW1');
        $token = $this->workoutToggleTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::WORKOUT_TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'shareWorkouts' => true,
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertTrue($this->getUserByEmail(self::ACTOR)->shareWorkouts);
    }

    public function testEnablingWorkoutSharingWhileNotDiscoverableIsRejected(): void
    {
        $client = $this->login(self::ACTOR);
        $token = $this->workoutToggleTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::WORKOUT_TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'shareWorkouts' => true,
                '_token' => $token,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertFalse($this->getUserByEmail(self::ACTOR)->shareWorkouts);
    }

    public function testInvalidCsrfTokenIsRejectedOnWorkoutToggle(): void
    {
        $client = $this->login(self::ACTOR);

        $client->request(
            Request::METHOD_PATCH,
            self::WORKOUT_TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'shareWorkouts' => true,
                '_token' => 'invalid',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDisablingProfileSharingCascadesToWorkoutSharing(): void
    {
        $client = $this->login(self::ACTOR);
        $actor = $this->getUserByEmail(self::ACTOR);
        $this->makeDiscoverable($actor, 'WWWWW2');
        $actor->shareWorkouts = true;
        $this->entityManager()->flush();
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
        self::assertFalse($this->getUserByEmail(self::ACTOR)->shareWorkouts);
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

    private function workoutToggleTokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            self::LIST_URL,
            '[data-profile-connection--profile-sharing-workout-toggle-token-value]',
            'data-profile-connection--profile-sharing-workout-toggle-token-value',
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
