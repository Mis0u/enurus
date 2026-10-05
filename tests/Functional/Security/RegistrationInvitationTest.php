<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

/**
 * Inscription par lien d'invitation (`/inscription?invitation=<shareCode>`) jusqu'à la confirmation
 * de l'email. L'email envoyé au parrain passe par la file `async`, testé au niveau du service
 * (`InvitationAcceptedNotifierTest`), pas ici.
 */
final class RegistrationInvitationTest extends WebTestCase
{
    private const string PASSWORD = 'pass_PASS?1234';

    private const string INVITER_EMAIL = 'user-fixture-26-workout@test.com';

    private const string INVITER_SHARE_CODE = 'HUBCDE';

    private const string INVITEE_EMAIL = 'invited-friend@test.com';

    private const string INVITEE_NICKNAME = 'Invitee';

    private const string BANNER = '#registration-invitation';

    private KernelBrowser $client;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->clock = new MockClock('2026-10-05 08:00:00');
        $this->client->getContainer()->set(ClockInterface::class, $this->clock);
    }

    public function testTheInvitationIsAnnouncedOnTheRegistrationPage(): void
    {
        $this->client->request(Request::METHOD_GET, '/fr/inscription?invitation=' . self::INVITER_SHARE_CODE);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains(self::BANNER, $this->inviter()->nickname);
        $this->assertSelectorTextContains(self::BANNER, 'automatiquement connectés');
        $this->assertSelectorTextContains(self::BANNER, 'partagé');
    }

    public function testAnUnknownCodeLeavesARegularRegistrationPage(): void
    {
        $this->client->request(Request::METHOD_GET, '/fr/inscription?invitation=ZZZZZZ');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists(self::BANNER);
    }

    public function testRegisteringFromTheLinkRemembersTheInviterUntilTheEmailIsConfirmed(): void
    {
        $invitee = $this->registerFrom('/fr/inscription?invitation=' . self::INVITER_SHARE_CODE);

        self::assertSame($this->inviter()->id?->toRfc4122(), $invitee->invitedBy?->id?->toRfc4122());
        self::assertNull($this->connectionBetween($this->inviter(), $invitee));
    }

    public function testRegisteringWithAnUnknownCodeRemembersNobody(): void
    {
        $invitee = $this->registerFrom('/fr/inscription?invitation=ZZZZZZ');

        self::assertNull($invitee->invitedBy);
    }

    public function testConfirmingTheEmailConnectsTheFriendsAndSharesTheInviteeProfile(): void
    {
        $invitee = $this->registerFrom('/fr/inscription?invitation=' . self::INVITER_SHARE_CODE);

        $this->confirmEmailOf($invitee);

        $invitee = $this->invitee();
        $connection = $this->connectionBetween($this->inviter(), $invitee);
        self::assertNotNull($connection);
        self::assertSame(ProfileConnectionStatusEnum::ACCEPTED, $connection->status);
        self::assertTrue($invitee->isDiscoverable);
        self::assertNotNull($invitee->shareCode);
    }

    public function testTheWelcomeMessageTellsTheInviteeWhoTheyAreConnectedTo(): void
    {
        $invitee = $this->registerFrom('/fr/inscription?invitation=' . self::INVITER_SHARE_CODE);

        $this->confirmEmailOf($invitee);

        self::assertStringContainsString($this->inviter()->nickname, $this->welcomeMessageOf($invitee)->body);
    }

    public function testTheWelcomeMessageOfARegularRegistrationMentionsNoConnection(): void
    {
        $invitee = $this->registerFrom('/fr/inscription');

        $this->confirmEmailOf($invitee);

        self::assertStringNotContainsString($this->inviter()->nickname, $this->welcomeMessageOf($invitee)->body);
    }

    public function testNicknamesCannotInjectMarkupIntoTheWelcomeMessage(): void
    {
        $this->inviter()->nickname = '<svg onload=x()>';
        $this->entityManager()->flush();
        $invitee = $this->registerFrom('/fr/inscription?invitation=' . self::INVITER_SHARE_CODE);

        $this->confirmEmailOf($invitee);

        $body = $this->welcomeMessageOf($invitee)->body;
        self::assertStringNotContainsString('<svg', $body);
        self::assertStringContainsString('&lt;svg onload=x()&gt;', $body);
    }

    private function registerFrom(string $url): User
    {
        $crawler = $this->client->request(Request::METHOD_GET, $url);
        $form = $crawler->selectButton('Créer mon compte')->form();

        $this->clock->sleep(5);

        $this->client->submit($form, [
            'registration_form[gender]' => 'male',
            'registration_form[nickname]' => self::INVITEE_NICKNAME,
            'registration_form[email]' => self::INVITEE_EMAIL,
            'registration_form[plainPassword]' => self::PASSWORD,
            'registration_form[website]' => null,
        ]);

        $this->assertResponseRedirects('/fr/inscription/verifier-email');

        return $this->invitee();
    }

    private function confirmEmailOf(User $user): void
    {
        /** @var VerifyEmailHelperInterface $verifyEmailHelper */
        $verifyEmailHelper = $this->client->getContainer()->get(VerifyEmailHelperInterface::class);
        $signature = $verifyEmailHelper->generateSignature('app_verify_email', (string) $user->id, $user->email, [
            '_locale' => 'fr',
            'id' => (string) $user->id,
        ]);

        $this->client->request(Request::METHOD_GET, $signature->getSignedUrl());

        $this->assertResponseRedirects('/fr/tableau-de-bord');
    }

    private function connectionBetween(User $inviter, User $invitee): ?ProfileConnection
    {
        return $this->entityManager()->getRepository(ProfileConnection::class)->findOneBy([
            'requester' => $inviter,
            'addressee' => $invitee,
        ]);
    }

    private function welcomeMessageOf(User $user): ContactThreadMessage
    {
        $thread = $this->entityManager()->getRepository(ContactThread::class)->findOneBy([
            'owner' => $user,
            'isWelcomeMessage' => true,
        ]) ?? throw new \LogicException('Welcome thread not found.');

        $message = $thread->messages->first();

        return $message instanceof ContactThreadMessage ? $message : throw new \LogicException('Welcome message not found.');
    }

    private function invitee(): User
    {
        return $this->userRepository()->findOneByEmail(self::INVITEE_EMAIL)
            ?? throw new \LogicException('Freshly registered user not found.');
    }

    private function inviter(): User
    {
        return $this->userRepository()->findOneByEmail(self::INVITER_EMAIL)
            ?? throw new \LogicException('Inviter fixture not found.');
    }

    private function userRepository(): UserRepository
    {
        /** @var UserRepository */
        return $this->entityManager()->getRepository(User::class);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return $this->client->getContainer()->get(EntityManagerInterface::class);
    }
}
