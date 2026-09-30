<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\YearInReview;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Tests\Functional\Helper\YearInReviewTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class YearInReviewAnnounceCommandTest extends KernelTestCase
{
    use ClockSensitiveTrait;

    public function testRefusesToSendBeforeSevenInTheMorning(): void
    {
        self::mockTime('2026-12-16 06:00:00 Europe/Paris');
        $commandTester = $this->commandTester();
        $review = $this->eligibleReview();

        $commandTester->execute([]);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertNull($review->emailedAt);
    }

    public function testSendsTheEmailsStillAwaiting(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $commandTester = $this->commandTester();
        $review = $this->eligibleReview();

        $commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        self::assertNotNull($review->emailedAt);
    }

    private function commandTester(): CommandTester
    {
        return new CommandTester(new Application(self::bootKernel())->find('app:year-in-review:announce'));
    }

    private function eligibleReview(): YearInReview
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $review = YearInReview::eligible(YearInReviewTestHelper::createVerifiedUser($em), 2026, new YearInReviewSnapshot(
            new YearInReviewTotals(12, 36, 360, 5400.0),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(2, 3, 5),
            [],
            null,
        ));
        $em->persist($review);
        $em->flush();

        return $review;
    }
}
