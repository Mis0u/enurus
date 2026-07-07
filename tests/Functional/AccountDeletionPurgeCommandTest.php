<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class AccountDeletionPurgeCommandTest extends KernelTestCase
{
    public function testCommandRemovesExpiredAccounts(): void
    {
        $kernel = self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->email = 'purge-command-test@test.com';
        $user->password = 'hashed';
        $user->nickname = 'PurgeTest';
        $user->lastLogin = new \DateTimeImmutable();
        $user->deletionRequestedAt = new \DateTimeImmutable('-35 days');

        $em->persist($user);
        $em->flush();

        $application = new Application($kernel);
        $command = $application->find('app:account-deletion:purge');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('1 compte', $commandTester->getDisplay());

        /** @var User|null $deletedUser */
        $deletedUser = $em->getRepository(User::class)->findOneBy([
            'email' => 'purge-command-test@test.com',
        ]);

        self::assertNull($deletedUser);
    }
}
