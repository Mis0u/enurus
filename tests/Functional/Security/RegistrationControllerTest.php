<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ContactThreadMessage;
use App\Entity\DeletedAccountTrace;
use App\Entity\User;
use App\Enum\Entity\User\GenderEnum;
use App\Repository\ContactThreadRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use function Symfony\Component\Clock\now;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class RegistrationControllerTest extends WebTestCase
{
    private const string PASSWORD = 'pass_PASS?1234';

    /**
     * @return array{array{'male'}, array{'female'}}
     */
    public static function genderProvider(): array
    {
        return [
            ['male'],
            ['female'],
        ];
    }

    public function testRegistrationPageIsAccessible(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/inscription');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(
            'title',
            'Inscription',
            'Le titre de la page ne contient pas le texte demandé'
        );
    }

    public function testHoneyPotIsRedirectToLoginPageWithoutPersistInDatabase(): void
    {
        $user = $this->fillField('male', 'bot@test.com', 5, '/fr/', 'Je suis un bot');
        $this->assertNull($user);
    }

    public function testHoneyPotFormSubmitToFastWithoutPersistInDatabase(): void
    {
        $user = $this->fillField('male', 'bot@test.com', 1, '/fr/');
        $this->assertNull($user);
    }

    #[DataProvider('genderProvider')]
    public function testRegistrationSuccess(string $gender): void
    {
        $user = $this->fillField($gender, 'no_bot@test.com', 5, '/fr/inscription/verifier-email');
        $this->assertNotNull($user);
        $this->assertInstanceOf(\DateTimeImmutable::class, $user->lastLogin);
        $this->assertSame($user->lastLogin->format('Y-m-d'), now()->format('Y-m-d'));
        $this->assertSame(GenderEnum::from($gender), $user->gender);
        $this->assertSame('fr', $user->locale);
        $this->assertFalse($user->isVerified, 'A freshly registered account must stay unverified until the confirmation link is clicked (TODO #24).');
    }

    public function testReregistrationWithEmailOfDeletedAccountNotifiesAdminInternally(): void
    {
        $email = 'rejoined-after-deletion@test.com';

        $client = static::createClient();
        $client->disableReboot();
        $mockClock = new MockClock('2026-01-29 08:46:00');
        $client->getContainer()->set(ClockInterface::class, $mockClock);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get(EntityManagerInterface::class);

        $trace = new DeletedAccountTrace();
        $trace->emailHash = hash('sha256', $email);
        $trace->deletedAt = new \DateTimeImmutable('-3 days');
        $entityManager->persist($trace);
        $entityManager->flush();

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

        /** @var UserRepository $userRepositoryForConfirmation */
        $userRepositoryForConfirmation = $entityManager->getRepository(User::class);
        $registeredUser = $userRepositoryForConfirmation->findOneByEmail($email);

        if (! $registeredUser instanceof User || null === $registeredUser->id) {
            throw new \LogicException('Freshly registered user not found.');
        }

        /** @var VerifyEmailHelperInterface $verifyEmailHelper */
        $verifyEmailHelper = $client->getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $verifyEmailHelper->generateSignature(
            'app_verify_email',
            (string) $registeredUser->id,
            $registeredUser->email,
            [
                '_locale' => 'fr',
                'id' => (string) $registeredUser->id,
            ],
        );

        $client->request(Request::METHOD_GET, $signature->getSignedUrl());
        $this->assertResponseRedirects('/fr/tableau-de-bord');

        /** @var ContactThreadRepository $contactThreadRepository */
        $contactThreadRepository = $client->getContainer()->get(ContactThreadRepository::class);
        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);

        $admin = $userRepository->findOneByEmail('admin-fixture@test.com');
        self::assertNotNull($admin);

        $adminThreads = $contactThreadRepository->findBy([
            'owner' => $admin,
        ]);
        $notificationThread = null;
        foreach ($adminThreads as $thread) {
            if (str_contains($thread->subject, 'Réinscription')) {
                $notificationThread = $thread;
            }
        }

        self::assertNotNull($notificationThread, 'Expected an internal messaging thread notifying the admin of the re-registration.');
        self::assertCount(1, $notificationThread->messages);

        $message = $notificationThread->messages->first();
        self::assertInstanceOf(ContactThreadMessage::class, $message);
        self::assertStringContainsString($email, $message->body);
    }

    public function testRedirectToDashboardIfUserIsAuthenticated(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var UserInterface $testUser */
        $testUser = $userRepository->findOneBy([
            'email' => 'user-fixture-0@test.com',
        ]);

        $client->loginUser($testUser);

        $client->request(Request::METHOD_GET, '/fr/inscription');
        $this->assertResponseRedirects('/fr/tableau-de-bord');
    }

    private function fillField(
        string $gender,
        string $email,
        int $sleepSeconds,
        string $route,
        ?string $honeyPot = null
    ): ?User {
        $client = static::createClient();
        $client->disableReboot(); // CONFLIT AVEC LE CLOCK DONC POUR CE TEST JAI DESACTIVE LE REBOOT
        $mockClock = new MockClock('2026-01-29 08:46:00');
        $client->getContainer()->set(ClockInterface::class, $mockClock); //OBLIGE DE SET SINON IL RECUPERE L'HEURE DU SYSTEME

        /** @var EntityManagerInterface $entityManager */
        $entityManager = $client->getContainer()->get('doctrine');
        $crawler = $client->request(Request::METHOD_GET, '/fr/inscription');

        $this->assertSelectorTextContains('button', 'Créer mon compte', 'Le sélecteur contenant \'Créer mon compte\' est introuvable');
        $buttonCrawlerNode = $crawler->selectButton('Créer mon compte');
        $form = $buttonCrawlerNode->form();

        /** @var UserRepository $userRepository */
        $userRepository = $entityManager->getRepository(User::class);

        $mockClock->sleep($sleepSeconds);

        $client->submit($form, [
            'registration_form[gender]' => $gender,
            'registration_form[nickname]' => 'Toto',
            'registration_form[email]' => $email,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[website]' => $honeyPot,
        ]);
        $this->assertResponseRedirects($route);
        $client->followRedirect();

        return $userRepository->findOneBy([
            'email' => $email,
        ]);
    }
}
