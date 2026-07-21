<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\DeletedAccountTrace;
use App\Repository\DeletedAccountTraceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class DeletedAccountTracePurgeCommandTest extends KernelTestCase
{
    public function testCommandRemovesExpiredTracesOnly(): void
    {
        $kernel = self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $expiredTrace = new DeletedAccountTrace();
        $expiredTrace->emailHash = hash('sha256', 'expired-trace-test@test.com');
        $expiredTrace->deletedAt = new \DateTimeImmutable('-35 days');

        $recentTrace = new DeletedAccountTrace();
        $recentTrace->emailHash = hash('sha256', 'recent-trace-test@test.com');
        $recentTrace->deletedAt = new \DateTimeImmutable('-5 days');

        $em->persist($expiredTrace);
        $em->persist($recentTrace);
        $em->flush();

        $application = new Application($kernel);
        $command = $application->find('app:deleted-account-trace:purge');
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        self::assertSame(0, $commandTester->getStatusCode());
        self::assertStringContainsString('1 trace', $commandTester->getDisplay());

        /** @var DeletedAccountTraceRepository $traceRepository */
        $traceRepository = static::getContainer()->get(DeletedAccountTraceRepository::class);

        self::assertNull($traceRepository->findByEmailHash($expiredTrace->emailHash));
        self::assertNotNull($traceRepository->findByEmailHash($recentTrace->emailHash));
    }
}
