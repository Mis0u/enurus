<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SettingsPasswordControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-1@test.com';

    private const string CURRENT_PASSWORD = 'pass_1234';

    private const string URL = '/fr/reglages/mot-de-passe';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_POST, self::URL);

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testChangeSucceedsWithCorrectCurrentPassword(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => self::CURRENT_PASSWORD,
            'change_password_form[plainPassword][first]' => 'NewValidPass123!',
            'change_password_form[plainPassword][second]' => 'NewValidPass123!',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        $this->assertResponseIsSuccessful();

        /** @var UserPasswordHasherInterface $hasher */
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        self::assertTrue($hasher->isPasswordValid($user, 'NewValidPass123!'));
        self::assertFalse($hasher->isPasswordValid($user, self::CURRENT_PASSWORD));
    }

    public function testChangeFailsWithIncorrectCurrentPassword(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => 'wrong-password',
            'change_password_form[plainPassword][first]' => 'NewValidPass123!',
            'change_password_form[plainPassword][second]' => 'NewValidPass123!',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);

        $data = $this->decodeJsonResponse($client);
        self::assertArrayHasKey('errors', $data);
        $errors = $data['errors'];

        if (! \is_array($errors)) {
            throw new \LogicException('Expected errors to be an array.');
        }

        self::assertArrayHasKey('currentPassword', $errors);
    }

    public function testChangeFailsWithTooShortNewPassword(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => self::CURRENT_PASSWORD,
            'change_password_form[plainPassword][first]' => 'Sh0rt!',
            'change_password_form[plainPassword][second]' => 'Sh0rt!',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);
    }

    public function testChangeFailsWithMismatchedConfirmation(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => self::CURRENT_PASSWORD,
            'change_password_form[plainPassword][first]' => 'NewValidPass123!',
            'change_password_form[plainPassword][second]' => 'DifferentPass456!',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);
    }

    public function testChangeFailsWithBlankCurrentPassword(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => '',
            'change_password_form[plainPassword][first]' => 'NewValidPass123!',
            'change_password_form[plainPassword][second]' => 'NewValidPass123!',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(422);
    }

    public function testSixthAttemptWithinWindowIsRateLimited(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/reglages');

        for ($i = 0; 5 > $i; $i++) {
            $form = $crawler->filter('form[name="change_password_form"]')->form([
                'change_password_form[currentPassword]' => 'wrong-password',
                'change_password_form[plainPassword][first]' => 'NewValidPass123!',
                'change_password_form[plainPassword][second]' => 'NewValidPass123!',
            ]);

            $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());
        }

        $form = $crawler->filter('form[name="change_password_form"]')->form([
            'change_password_form[currentPassword]' => 'wrong-password',
            'change_password_form[plainPassword][first]' => 'NewValidPass123!',
            'change_password_form[plainPassword][second]' => 'NewValidPass123!',
        ]);

        $client->request($form->getMethod(), $form->getUri(), $form->getPhpValues(), $form->getPhpFiles());

        self::assertResponseStatusCodeSame(429);

        $data = $this->decodeJsonResponse($client);
        self::assertArrayHasKey('error', $data);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonResponse(KernelBrowser $client): array
    {
        /** @var mixed $data */
        $data = json_decode((string) $client->getResponse()->getContent(), true);

        if (! \is_array($data)) {
            throw new \LogicException('Expected JSON response to decode to an array.');
        }

        /** @var array<string, mixed> $data */
        return $data;
    }
}
