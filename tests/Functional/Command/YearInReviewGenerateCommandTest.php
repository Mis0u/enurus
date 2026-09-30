<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\YearInReview;
use App\Tests\Functional\Helper\YearInReviewTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class YearInReviewGenerateCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testRefusesAYearNotPublishedYet(): void
    {
        self::mockTime('2026-11-10 12:00:00 Europe/Paris');
        $commandTester = $this->commandTester();

        $commandTester->execute([
            'year' => '2026',
        ]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertCount(0, $this->entityManager()->getRepository(YearInReview::class)->findBy([
            'year' => 2026,
        ]));
    }

    public function testGeneratesTheReviewOfASingleUserOnlyOnce(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $commandTester = $this->commandTester();
        $em = $this->entityManager();
        $user = YearInReviewTestHelper::createVerifiedUser($em);
        $otherUser = YearInReviewTestHelper::createVerifiedUser($em);

        $commandTester->execute([
            '--user' => $user->email,
        ]);
        $commandTester->execute([
            '--user' => $user->email,
        ]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertStringContainsString('existe déjà', (string) preg_replace('/[\s!]+/', ' ', $commandTester->getDisplay()));
        $reviews = $em->getRepository(YearInReview::class);
        self::assertCount(1, $reviews->findBy([
            'owner' => $user,
            'year' => 2026,
        ]));
        self::assertCount(0, $reviews->findBy([
            'owner' => $otherUser,
        ]));
    }

    public function testGeneratesTheMissingReviewsOfEveryone(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $commandTester = $this->commandTester();
        $em = $this->entityManager();
        $user = YearInReviewTestHelper::createVerifiedUser($em);

        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertCount(1, $em->getRepository(YearInReview::class)->findBy([
            'owner' => $user,
            'year' => 2026,
        ]));
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('app:year-in-review:generate'));
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
