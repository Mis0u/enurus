<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class UserRepositoryTest extends KernelTestCase
{
    public function testFindPendingDeletionOlderThanReturnsExpiredUsers(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $expiredUser = $this->createTestUser($em, 'expired-user@test.com', new \DateTimeImmutable('-35 days'));
        $recentUser = $this->createTestUser($em, 'recent-user@test.com', new \DateTimeImmutable('-10 days'));
        $noRequestUser = $this->createTestUser($em, 'no-request-user@test.com', null);
        $exactBoundaryUser = $this->createTestUser($em, 'boundary-user@test.com', new \DateTimeImmutable('-30 days'));

        $threshold = new \DateTimeImmutable('-30 days');
        $results = $userRepository->findPendingDeletionOlderThan($threshold);
        $resultEmails = array_map(static fn (User $u): string => $u->email, $results);

        self::assertContains('expired-user@test.com', $resultEmails);
        self::assertContains('boundary-user@test.com', $resultEmails);
        self::assertNotContains('recent-user@test.com', $resultEmails);
        self::assertNotContains('no-request-user@test.com', $resultEmails);

        $em->remove($expiredUser);
        $em->remove($recentUser);
        $em->remove($noRequestUser);
        $em->remove($exactBoundaryUser);
        $em->flush();
    }

    public function testCountLoggedInSinceCountsOnlyUsersLoggedInAfterThreshold(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $recentLogin = $this->createTestUser($em, 'recent-login-user@test.com', null, new \DateTimeImmutable('-1 hour'));
        $oldLogin = $this->createTestUser($em, 'old-login-user@test.com', null, new \DateTimeImmutable('-3 days'));

        $threshold = new \DateTimeImmutable('-1 day');
        $countBefore = $userRepository->countLoggedInSince($threshold);

        self::assertGreaterThanOrEqual(1, $countBefore);

        $em->remove($recentLogin);
        $em->remove($oldLogin);
        $em->flush();

        $countAfter = $userRepository->countLoggedInSince($threshold);

        self::assertSame($countBefore - 1, $countAfter);
    }

    private function createTestUser(
        EntityManagerInterface $em,
        string $email,
        ?\DateTimeImmutable $deletionRequestedAt,
        ?\DateTimeImmutable $lastLogin = null,
    ): User {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'TestUser';
        $user->lastLogin = $lastLogin ?? new \DateTimeImmutable();
        $user->deletionRequestedAt = $deletionRequestedAt;

        $em->persist($user);
        $em->flush();

        return $user;
    }
}
