<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class SettingsAvatarUploadControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string URL = '/fr/reglages/avatar';

    private const string SETTINGS_URL = '/fr/reglages';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_POST, self::URL);

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testUploadSucceedsWithValidJpeg(): void
    {
        $client = $this->login(self::USER);
        $file = ImageTestHelper::createFakeImage('avatar.jpg', 'image/jpeg');

        $this->upload($client, $file);

        $this->assertResponseIsSuccessful();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        self::assertNotNull($user->avatarPath);
    }

    public function testUploadSucceedsWithValidPng(): void
    {
        $client = $this->login(self::USER);
        $file = ImageTestHelper::createFakeImage('avatar.png', 'image/png');

        $this->upload($client, $file);

        $this->assertResponseIsSuccessful();
    }

    public function testUploadFailsWithoutFile(): void
    {
        $client = $this->login(self::USER);

        $this->upload($client, null);

        self::assertResponseStatusCodeSame(400);
    }

    public function testUploadIsRefusedWithoutAValidCsrfToken(): void
    {
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_POST, self::URL, [], [
            'avatar' => ImageTestHelper::createFakeImage('avatar.jpg', 'image/jpeg'),
        ], [
            'HTTP_X-CSRF-Token' => 'forged',
        ]);

        self::assertResponseStatusCodeSame(403);
    }

    public function testUploadFailsWithInvalidMimeType(): void
    {
        $client = $this->login(self::USER);
        $file = ImageTestHelper::createFakeImage('document.pdf', 'application/pdf');

        $this->upload($client, $file);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUploadFailsWithFileTooLarge(): void
    {
        $client = $this->login(self::USER);
        $file = ImageTestHelper::createLargeJpeg();

        $this->upload($client, $file);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUploadReplacesExistingAvatar(): void
    {
        $client = $this->login(self::USER);

        $firstFile = ImageTestHelper::createFakeImage('first.jpg', 'image/jpeg');
        $this->upload($client, $firstFile);
        $this->assertResponseIsSuccessful();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);
        $firstPath = $user->avatarPath;

        $secondFile = ImageTestHelper::createFakeImage('second.jpg', 'image/jpeg');
        $this->upload($client, $secondFile);
        $this->assertResponseIsSuccessful();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $updatedUser */
        $updatedUser = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        self::assertNotSame($firstPath, $updatedUser->avatarPath);
    }

    private function upload(KernelBrowser $client, ?UploadedFile $file): void
    {
        $token = $this->csrfTokenFromPage(
            $client,
            self::SETTINGS_URL,
            '[data-controller="settings--avatar"]',
            'data-settings--avatar-upload-csrf-token-value',
        );

        $client->request(Request::METHOD_POST, self::URL, [], null === $file ? [] : [
            'avatar' => $file,
        ], [
            'HTTP_X-CSRF-Token' => $token,
        ]);
    }
}
