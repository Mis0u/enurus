<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsNicknameControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string URL = '/fr/reglages/pseudo';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_PATCH, self::URL, content: $this->toJson([
            'nickname' => 'Toto',
        ]));

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testUpdateSucceedsWithValidNickname(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'nickname' => 'NewAlias',
                '_token' => $token,
            ]),
        );

        $this->assertResponseIsSuccessful();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        self::assertSame('NewAlias', $user->nickname);
    }

    public function testUpdateFailsWithTooShortNickname(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'nickname' => 'ab',
                '_token' => $token,
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateFailsWithTooLongNickname(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'nickname' => str_repeat('a', 21),
                '_token' => $token,
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateFailsWithBlankNickname(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'nickname' => '',
                '_token' => $token,
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateFailsWithInvalidCsrfToken(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'nickname' => 'ValidAlias',
                '_token' => 'invalid-token',
            ]),
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateSucceedsWithUnicodeNickname(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        // 5 caractères réels (mb_strlen), pas 6 octets — vérifie que la validation compte bien en unicode
        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'nickname' => 'Émoji',
                '_token' => $token,
            ]),
        );

        $this->assertResponseIsSuccessful();
    }

    private function generateCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $node = $crawler->filter('[data-settings--nickname-csrf-token-value]')->first();

        return (string) $node->attr('data-settings--nickname-csrf-token-value');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
