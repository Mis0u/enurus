<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Enum\Entity\User\GenderEnum;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsGenderControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string URL = '/fr/reglages/genre';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_PATCH, self::URL, content: $this->toJson([
            'gender' => 'male',
        ]));

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testUpdateSucceedsWithMale(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'gender' => 'male',
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

        self::assertSame(GenderEnum::MALE, $user->gender);
    }

    public function testUpdateSucceedsWithFemale(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'gender' => 'female',
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

        self::assertSame(GenderEnum::FEMALE, $user->gender);
    }

    public function testUpdateFailsWithInvalidValue(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'gender' => 'autre',
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
                'gender' => 'male',
                '_token' => 'invalid-token',
            ]),
        );

        self::assertResponseStatusCodeSame(403);
    }

    private function generateCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $node = $crawler->filter('[data-settings--select-field-param-name-value="gender"]')->first();

        return (string) $node->attr('data-settings--select-field-csrf-token-value');
    }

    /**
     * @param array<string, mixed> $data
     */
    private function toJson(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }
}
