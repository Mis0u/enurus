<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * TODO #24 — `app:unverified-account:purge`.
 */
final class UnverifiedAccountPurgeCommandTest extends KernelTestCase
{
    public function testCommandRemovesUnverifiedAccountsPastGracePeriod(): void
    {
        $kernel = self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $expiredUnverified = $this->makeUser('expired-unverified@test.com', isVerified: false, createdAt: '-8 days');
        $recentUnverified = $this->makeUser('recent-unverified@test.com', isVerified: false, createdAt: '-1 day');
        $oldButVerified = $this->makeUser('old-verified@test.com', isVerified: true, createdAt: '-30 days');

        $em->persist($expiredUnverified);
        $em->persist($recentUnverified);
        $em->persist($oldButVerified);
        $em->flush();

        $application = new Application($kernel);
        $command = $application->find('app:unverified-account:purge');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('1 compte', $commandTester->getDisplay());

        $userRepository = $em->getRepository(User::class);

        self::assertNull($userRepository->findOneBy([
            'email' => 'expired-unverified@test.com',
        ]));
        self::assertNotNull($userRepository->findOneBy([
            'email' => 'recent-unverified@test.com',
        ]), 'A recently registered unverified account must survive the grace period.');
        self::assertNotNull($userRepository->findOneBy([
            'email' => 'old-verified@test.com',
        ]), 'A verified account must never be purged, regardless of age.');
    }

    private function makeUser(string $email, bool $isVerified, string $createdAt): User
    {
        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'PurgeTest';
        $user->lastLogin = new \DateTimeImmutable();
        $user->isVerified = $isVerified;
        $user->createdAt = new \DateTimeImmutable($createdAt);

        return $user;
    }
}
