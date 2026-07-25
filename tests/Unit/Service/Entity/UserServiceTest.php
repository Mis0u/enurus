<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\User;
use App\Service\Email\EmailInterface;
use App\Service\Entity\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class UserServiceTest extends TestCase
{
    public function testChangePasswordSucceedsWithCorrectCurrentPassword(): void
    {
        $user = new User();
        $user->email = 'user@example.com';

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('isPasswordValid')
            ->with($user, 'correct-current-password')
            ->willReturn(true);
        $passwordHasher->expects(self::once())
            ->method('hashPassword')
            ->with($user, 'NewValidPassword123!')
            ->willReturn('hashed-new-password');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($user);
        $em->expects(self::once())->method('flush');

        $templatedEmail = new TemplatedEmail();

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('Mot de passe modifié');

        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with(
                'user@example.com',
                'Mot de passe modifié',
                self::callback(static fn (array $context): bool => $context['user'] === $user),
                'emails/password_changed.html.twig',
                $user->locale,
            )
            ->willReturn($templatedEmail);
        $emailService->expects(self::once())->method('sendEmail')->with($templatedEmail);

        $userService = new UserService($passwordHasher, $em, $emailService, $translator);

        $result = $userService->changePassword($user, 'correct-current-password', 'NewValidPassword123!');

        self::assertTrue($result);
        self::assertSame('hashed-new-password', $user->password);
        self::assertSame('sync', $templatedEmail->getHeaders()->get('X-Bus-Transport')?->getBodyAsString());
    }

    public function testChangePasswordFailsWithIncorrectCurrentPassword(): void
    {
        $user = new User();

        $passwordHasher = $this->createMock(UserPasswordHasherInterface::class);
        $passwordHasher->expects(self::once())
            ->method('isPasswordValid')
            ->with($user, 'wrong-current-password')
            ->willReturn(false);
        $passwordHasher->expects(self::never())->method('hashPassword');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');
        $em->expects(self::never())->method('flush');

        $translator = $this->createStub(TranslatorInterface::class);
        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('createEmail');
        $emailService->expects(self::never())->method('sendEmail');

        $userService = new UserService($passwordHasher, $em, $emailService, $translator);

        $result = $userService->changePassword($user, 'wrong-current-password', 'NewValidPassword123!');

        self::assertFalse($result);
    }
}
