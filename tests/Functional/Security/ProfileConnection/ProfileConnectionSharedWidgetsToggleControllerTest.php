<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ProfileConnectionSharedWidgetsToggleControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use ProfileConnectionTestTrait;

    private const string URL = '/fr/connexions/partage/widgets';

    // 26 séances : débloque Session, Tonnage, Muscles, Régularité.
    private const string USER_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    public function testHidingAWidgetForSharingPersistsItInHiddenSharedWidgets(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeSharingActive();
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertContains('tonnage', $this->getHiddenSharedWidgets());
    }

    public function testHidingForSharingNeverTouchesHiddenWidgets(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeSharingActive();
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertNotContains('tonnage', $this->getUserByEmail(self::USER_WITH_WORKOUTS)->hiddenWidgets);
    }

    public function testReshowingRemovesItFromHiddenSharedWidgets(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeSharingActive();
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );
        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => false,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertNotContains('tonnage', $this->getHiddenSharedWidgets());
    }

    public function testInvalidWidgetIsRejected(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeSharingActive();
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'not_a_real_widget',
                'hiddenForShare' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPersonalWidgetCannotBeSharedWithConnections(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeSharingActive();
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'connections',
                'hiddenForShare' => false,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeSharingActive();

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => true,
                '_token' => 'invalid',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReshowingIsRejectedWhenProfileSharingIsDisabled(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => false,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testReshowingIsRejectedWhenWorkoutSharingIsDisabled(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $this->makeDiscoverable($this->getUserByEmail(self::USER_WITH_WORKOUTS), 'SWCODE');
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => false,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testHidingIsStillAllowedWhenSharingIsDisabled(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $csrfToken = $this->csrfTokenFor($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'widget' => 'tonnage',
                'hiddenForShare' => true,
                '_token' => $csrfToken,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertContains('tonnage', $this->getHiddenSharedWidgets());
    }

    private function makeSharingActive(): void
    {
        $user = $this->getUserByEmail(self::USER_WITH_WORKOUTS);
        $this->makeDiscoverable($user, 'SWCODE');
        $user->shareWorkouts = true;
        $this->entityManager()->flush();
    }

    private function csrfTokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            self::LIST_URL,
            '[data-profile-connection--shared-widgets-csrf-token-value]',
            'data-profile-connection--shared-widgets-csrf-token-value',
        );
    }

    /**
     * @return array<string>
     */
    private function getHiddenSharedWidgets(): array
    {
        return $this->getUserByEmail(self::USER_WITH_WORKOUTS)->hiddenSharedWidgets;
    }
}
