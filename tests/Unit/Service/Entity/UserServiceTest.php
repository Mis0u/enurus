<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Entity;

use App\Entity\User;
use App\Service\Entity\UserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class UserServiceTest extends TestCase
{
    public function testChangePasswordSucceedsWithCorrectCurrentPassword(): void
    {
        $user = new User();

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

        $userService = new UserService($passwordHasher, $em);

        $result = $userService->changePassword($user, 'correct-current-password', 'NewValidPassword123!');

        self::assertTrue($result);
        self::assertSame('hashed-new-password', $user->password);
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

        $userService = new UserService($passwordHasher, $em);

        $result = $userService->changePassword($user, 'wrong-current-password', 'NewValidPassword123!');

        self::assertFalse($result);
    }
}
