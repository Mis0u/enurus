<?php

declare(strict_types=1);

namespace App\Tests\Functional\Helper;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class ProfileSharingTestHelper
{
    public static function createSearchableUser(
        EntityManagerInterface $entityManager,
        string $email,
        string $nickname,
        string $shareCode,
    ): User {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = $nickname;
        $user->lastLogin = new \DateTimeImmutable();
        $user->isVerified = true;
        $user->isDiscoverable = true;
        $user->shareCode = $shareCode;

        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
