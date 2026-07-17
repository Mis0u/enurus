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
use Symfony\Component\HttpFoundation\Request;

final class SettingsAvatarDeleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string UPLOAD_URL = '/fr/reglages/avatar';

    private const string DELETE_URL = '/fr/reglages/avatar';

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_DELETE, self::DELETE_URL);

        self::assertContains($client->getResponse()->getStatusCode(), [302, 401]);
    }

    public function testDeleteWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_DELETE, self::DELETE_URL);

        self::assertResponseStatusCodeSame(400);
    }

    public function testDeleteWithInvalidCsrfTokenIsRejected(): void
    {
        $this->deleteRequest($this->login(self::USER), self::DELETE_URL, 'invalid-token');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeleteSucceedsWhenAvatarExists(): void
    {
        $client = $this->login(self::USER);

        $file = ImageTestHelper::createFakeImage('avatar.jpg', 'image/jpeg');
        $client->request(Request::METHOD_POST, self::UPLOAD_URL, [], [
            'avatar' => $file,
        ]);
        $this->assertResponseIsSuccessful();

        $this->deleteRequest($client, self::DELETE_URL, $this->getDeleteCsrfToken($client));
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

        self::assertNull($updatedUser->avatarPath);
    }

    public function testDeleteIsIdempotentWhenNoAvatarExists(): void
    {
        $client = $this->login(self::USER);

        $this->deleteRequest($client, self::DELETE_URL, $this->getDeleteCsrfToken($client));

        $this->assertResponseIsSuccessful();
    }

    private function getDeleteCsrfToken(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage(
            $client,
            '/fr/reglages',
            'div[data-settings--avatar-delete-csrf-token-value]',
            'data-settings--avatar-delete-csrf-token-value',
        );
    }
}
