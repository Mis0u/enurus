<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;

/**
 * TODO #24 — page "vérifie ta boîte mail" et renvoi de l'email de confirmation.
 */
final class RegistrationCheckEmailFlowTest extends WebTestCase
{
    use FunctionalTestTrait;
    use MailerAssertionsTrait;

    private const string PASSWORD = 'pass_PASS?1234';

    public function testCheckEmailPageWithoutPendingRegistrationRedirectsToRegister(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/inscription/verifier-email');

        $this->assertResponseRedirects('/fr/inscription');
    }

    public function testCheckEmailPageDisplaysPendingEmailAfterRegistration(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $email = 'check-email-page@test.com';
        $this->registerUser($client, $email);

        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription/verifier-email');
        $this->assertResponseIsSuccessful();
        self::assertStringContainsString($email, $crawler->filter('body')->text());
        self::assertCount(1, $crawler->filter('form[action="/fr/inscription/renvoyer-confirmation"]'));
    }

    public function testResendWithoutPendingRegistrationRedirectsToRegister(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_POST, '/fr/inscription/renvoyer-confirmation', [
            '_token' => 'irrelevant',
        ]);

        $this->assertResponseRedirects('/fr/inscription');
    }

    public function testResendWithInvalidCsrfTokenIsDenied(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $this->registerUser($client, 'resend-bad-csrf@test.com');

        $client->request(Request::METHOD_POST, '/fr/inscription/renvoyer-confirmation', [
            '_token' => 'not-a-valid-token',
        ]);

        self::assertSame(403, $client->getResponse()->getStatusCode());
    }

    public function testResendSendsANewConfirmationEmail(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $email = 'resend-me@test.com';
        $this->registerUser($client, $email);

        /**
         * Le listener mailer (`mailer.message_logger_listener`, tag `kernel.reset`) est vidé à
         * chaque nouvelle requête du client de test — on ne peut donc comparer un total cumulé
         * d'une requête à l'autre (cf. commentaire équivalent dans `ResetPasswordTest`), seulement
         * vérifier ce qu'a produit la dernière requête en date.
         */
        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription/verifier-email');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        $client->request(Request::METHOD_POST, '/fr/inscription/renvoyer-confirmation', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/fr/inscription/verifier-email');
        $this->assertQueuedEmailCount(1, message: 'Resending must send a new confirmation email.');
    }

    public function testResendForAlreadyVerifiedUserDoesNotSendAnotherEmail(): void
    {
        $client = static::createClient();
        $client->disableReboot();

        $email = 'resend-already-verified@test.com';
        $this->registerUser($client, $email);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);
        $user = $userRepository->findOneByEmail($email);
        self::assertNotNull($user);
        $user->isVerified = true;
        $entityManager->flush();

        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription/verifier-email');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertNotNull($token);

        $client->request(Request::METHOD_POST, '/fr/inscription/renvoyer-confirmation', [
            '_token' => $token,
        ]);

        $this->assertResponseRedirects('/fr/inscription/verifier-email');
        $this->assertQueuedEmailCount(0, message: 'An already-verified account must not receive another confirmation email.');
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
}
