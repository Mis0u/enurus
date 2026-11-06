<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Entity\ContactThread;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Service\Contact\AdminMessageEmailNotifier;
use App\Service\Email\EmailInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;

final class AdminMessageEmailNotifierTest extends TestCase
{
    public function testSendsTheEmailToTheThreadOwnerInTheirLanguage(): void
    {
        $thread = $this->createThread($this->createRecipient());
        $threadId = Uuid::v7();
        $thread->id = $threadId;

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with(
                'recipient@test.com',
                'subject-in-de',
                [
                    'threadId' => $threadId->toRfc4122(),
                    'locale' => 'de',
                ],
                'emails/admin_message.html.twig',
                'de',
            )
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $key, array $parameters, ?string $domain, ?string $locale): string => 'messagerie.email.subject' === $key && 'de' === $locale ? 'subject-in-de' : 'brand',
        );

        new AdminMessageEmailNotifier($emailService, $translator, $this->createStub(LoggerInterface::class))->notify($thread);
    }

    /**
     * @return iterable<string, array{\Closure(User): void}>
     */
    public static function recipientsWhoGetNoEmail(): iterable
    {
        yield 'turned off' => [static function (User $user): void {
            $user->emailOnAdminMessage = false;
        }];
        yield 'not verified' => [static function (User $user): void {
            $user->isVerified = false;
        }];
        yield 'blocked' => [static function (User $user): void {
            $user->accountBlockedAt = new \DateTimeImmutable();
        }];
        yield 'admin' => [static function (User $user): void {
            $user->setRoles(['ROLE_ADMIN']);
        }];
        yield 'deletion requested' => [static function (User $user): void {
            $user->deletionRequestedAt = new \DateTimeImmutable();
        }];
    }

    /**
     * @param \Closure(User): void $alterRecipient
     */
    #[DataProvider('recipientsWhoGetNoEmail')]
    public function testSendsNothingTo(\Closure $alterRecipient): void
    {
        $recipient = $this->createRecipient();
        $alterRecipient($recipient);

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('sendEmail');

        new AdminMessageEmailNotifier(
            $emailService,
            $this->createStub(TranslatorInterface::class),
            $this->createStub(LoggerInterface::class),
        )->notify($this->createThread($recipient));
    }

    public function testAnyFailureIsLoggedAndNeverRethrown(): void
    {
        $emailService = $this->createStub(EmailInterface::class);
        $emailService->method('createEmail')->willReturn(new TemplatedEmail());
        $emailService->method('sendEmail')->willThrowException(new \RuntimeException('Queue unavailable'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        new AdminMessageEmailNotifier(
            $emailService,
            $this->createStub(TranslatorInterface::class),
            $logger,
        )->notify($this->createThread($this->createRecipient()));
    }

    private function createRecipient(): User
    {
        $user = new User();
        $user->email = 'recipient@test.com';
        $user->nickname = 'Recipient';
        $user->locale = 'de';
        $user->isVerified = true;

        return $user;
    }

    private function createThread(User $owner): ContactThread
    {
        $thread = new ContactThread();
        $thread->owner = $owner;
        $thread->category = ContactCategoryEnum::INFORMATIVE;
        $thread->subject = 'Sujet';

        return $thread;
    }
}
