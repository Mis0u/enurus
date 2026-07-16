<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsLanguageControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string URL = '/fr/reglages/langue';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_POST, self::URL, content: $this->toJson([
            'locale' => 'en',
        ]));

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testUpdateSucceedsAndReturnsRedirectUrl(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_POST,
            self::URL,
            content: $this->toJson([
                'locale' => 'en',
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

        self::assertSame('en', $user->locale);

        /** @var mixed $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        if (! \is_array($data)) {
            throw new \LogicException('Expected JSON response to decode to an array.');
        }

        self::assertArrayHasKey('redirectUrl', $data);

        $redirectUrl = $data['redirectUrl'];

        if (! \is_string($redirectUrl)) {
            throw new \LogicException('Expected redirectUrl to be a string.');
        }

        self::assertStringContainsString('/en/', $redirectUrl);
    }

    public function testUpdateFailsWithUnsupportedLocale(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_POST,
            self::URL,
            content: $this->toJson([
                'locale' => 'zz',
                '_token' => $token,
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateFailsWithInvalidCsrfToken(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_POST,
            self::URL,
            content: $this->toJson([
                'locale' => 'en',
                '_token' => 'invalid-token',
            ]),
        );

        self::assertResponseStatusCodeSame(403);
    }

    private function generateCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $node = $crawler->filter('[data-settings--language-csrf-token-value]')->first();

        return (string) $node->attr('data-settings--language-csrf-token-value');
    }
}
