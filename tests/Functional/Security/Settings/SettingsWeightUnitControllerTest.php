<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsWeightUnitControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string URL = '/fr/reglages/unite-poids';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_PATCH, self::URL, content: $this->toJson([
            'unit' => 'kg',
        ]));

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testUpdateSucceedsWithKg(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'unit' => 'kg',
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

        self::assertSame(UnitOfMeasureEnum::KG, $user->unitOfMeasure);
    }

    public function testUpdateSucceedsWithLbs(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'unit' => 'lbs',
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

        self::assertSame(UnitOfMeasureEnum::LBS, $user->unitOfMeasure);
    }

    public function testUpdateFailsWithInvalidValue(): void
    {
        $client = $this->login(self::USER);
        $token = $this->generateCsrfToken($client);

        $client->request(
            Request::METHOD_PATCH,
            self::URL,
            content: $this->toJson([
                'unit' => 'tonnes',
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
                'unit' => 'kg',
                '_token' => 'invalid-token',
            ]),
        );

        self::assertResponseStatusCodeSame(403);
    }

    private function generateCsrfToken(KernelBrowser $client): string
    {
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');
        $node = $crawler->filter('[data-settings--select-field-param-name-value="unit"]')->first();

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
