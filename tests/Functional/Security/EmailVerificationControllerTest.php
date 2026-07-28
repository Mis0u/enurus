<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * TODO #24 — le clic sur le lien de confirmation reçu à l'inscription.
 */
final class EmailVerificationControllerTest extends WebTestCase
{
    private const string PASSWORD = 'pass_PASS?1234';

    public function testValidLinkVerifiesUserLogsInAndRedirectsToDashboard(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $email = 'verify-me@test.com';
        $this->registerUser($client, $email);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneByEmail($email);

        self::assertNotNull($user);
        self::assertFalse($user->isVerified);

        $client->request(Request::METHOD_GET, $this->signedUrlFor($client, $user));

        $this->assertResponseRedirects('/fr/tableau-de-bord');

        $verifiedUser = $userRepository->findOneByEmail($email);
        self::assertNotNull($verifiedUser);
        self::assertTrue($verifiedUser->isVerified);
    }

    public function testMissingIdRedirectsToLoginWithError(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/verifier-email?token=whatever&expires=' . (time() + 3600));

        $this->assertResponseRedirects('/fr/');
        $crawler = $client->followRedirect();
        self::assertStringContainsString('invalide', $crawler->filter('body')->text());
    }

    public function testUnknownUserIdRedirectsToLogin(): void
    {
        $client = static::createClient();
        $unknownId = Uuid::v7();

        $client->request(
            Request::METHOD_GET,
            '/fr/verifier-email?id=' . $unknownId . '&token=whatever&expires=' . (time() + 3600)
        );

        $this->assertResponseRedirects('/fr/');
    }

    public function testTamperedSignatureRedirectsToCheckEmailWithError(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $email = 'tampered-link@test.com';
        $this->registerUser($client, $email);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneByEmail($email);
        self::assertNotNull($user);

        $tamperedUrl = $this->signedUrlFor($client, $user) . '_tampered';

        $client->request(Request::METHOD_GET, $tamperedUrl);

        $this->assertResponseRedirects('/fr/inscription/verifier-email');

        $reloadedUser = $userRepository->findOneByEmail($email);
        self::assertNotNull($reloadedUser);
        self::assertFalse($reloadedUser->isVerified, 'A tampered link must never verify the account.');
    }

    public function testAlreadyVerifiedUserRedirectsToLoginWithoutReTriggeringOnboarding(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $email = 'already-verified@test.com';
        $this->registerUser($client, $email);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneByEmail($email);
        self::assertNotNull($user);

        $signedUrl = $this->signedUrlFor($client, $user);

        $user->isVerified = true;
        $entityManager->flush();

        $client->request(Request::METHOD_GET, $signedUrl);

        $this->assertResponseRedirects('/fr/');

        $reloadedUser = $userRepository->findOneByEmail($email);
        self::assertNotNull($reloadedUser);
        self::assertTrue($reloadedUser->isVerified, 'Revisiting the link must not un-verify the account.');
    }

    private function registerUser(KernelBrowser $client, string $email): void
    {
        $mockClock = new MockClock('2026-01-29 08:46:00');
        $client->getContainer()->set(ClockInterface::class, $mockClock);

        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription');
        $buttonCrawlerNode = $crawler->selectButton('Créer mon compte');
        $form = $buttonCrawlerNode->form();

        $mockClock->sleep(5);

        $client->submit($form, [
            'registration_form[gender]' => 'male',
            'registration_form[nickname]' => 'Toto',
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[website]' => null,
        ]);

        $this->assertResponseRedirects('/fr/inscription/verifier-email');
    }

    private function signedUrlFor(KernelBrowser $client, User $user): string
    {
        /** @var VerifyEmailHelperInterface $verifyEmailHelper */
        $verifyEmailHelper = $client->getContainer()->get(VerifyEmailHelperInterface::class);

        $signature = $verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $user->id,
            $user->email,
            [
                '_locale' => 'fr',
                'id' => (string) $user->id,
            ],
        );

        return $signature->getSignedUrl();
    }
}
