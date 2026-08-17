<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsBodyweightControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-51-workout@test.com';

    private const string URL = '/fr/reglages/poids-du-corps';

    public function testUpdateSucceedsWithValidBodyweight(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'bodyweight' => '180',
                '_token' => $token,
            ]),
        );

        $this->assertResponseIsSuccessful();

        $user = $this->getUser();

        self::assertNotNull($user->bodyweightKg);
    }

    public function testUpdateFailsWithOutOfRangeBodyweight(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'bodyweight' => '5',
                '_token' => $token,
            ]),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateFailsWithNonNumericBodyweight(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'bodyweight' => 'abc',
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
                'bodyweight' => '180',
                '_token' => 'invalid-token',
            ]),
        );

        self::assertResponseStatusCodeSame(403);
    }

    public function testEmptyBodyweightClearsExistingValue(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'bodyweight' => '180',
                '_token' => $token,
            ]),
        );
        self::assertNotNull($this->getUser()->bodyweightKg);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'bodyweight' => '',
                '_token' => $token,
            ]),
        );

        $this->assertResponseIsSuccessful();
        self::assertNull($this->getUser()->bodyweightKg);
    }

    private function getUser(): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        return $user;
    }

    private function generateCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $node = $crawler->filter('[data-settings--bodyweight-csrf-token-value]')->first();

        return (string) $node->attr('data-settings--bodyweight-csrf-token-value');
    }
}
