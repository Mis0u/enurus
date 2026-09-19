<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SettingsDashboardWidgetSharingControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-26-workout@test.com';

    private const string URL = '/fr/reglages/widgets/partage';

    public function testHidingAWidgetForSharingPersistsItInHiddenSharedWidgets(): void
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
        $client = $this->login(self::USER);
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
        self::assertNotContains('tonnage', $this->getHiddenWidgets());
    }

    public function testReshowingAPreviouslyHiddenForSharingWidgetRemovesItFromHiddenSharedWidgets(): void
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

        self::assertNotContains('tonnage', $this->getHiddenSharedWidgets());
    }

    public function testInvalidWidgetIsRejected(): void
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
                'widget' => 'not_a_real_widget',
                'hiddenForShare' => true,
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
                'widget' => 'tonnage',
                'hiddenForShare' => true,
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
            '[data-settings--dashboard-widgets-share-csrf-token-value]',
            'data-settings--dashboard-widgets-share-csrf-token-value',
        );
    }

    /**
     * @return array<string>
     */
    private function getHiddenSharedWidgets(): array
    {
        return $this->reloadUser()->hiddenSharedWidgets;
    }

    /**
     * @return array<string>
     */
    private function getHiddenWidgets(): array
    {
        return $this->reloadUser()->hiddenWidgets;
    }

    private function reloadUser(): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);
        self::assertNotNull($user);

        return $user;
    }
}
