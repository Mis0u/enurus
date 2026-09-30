<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Tests\Functional\Helper\YearInReviewTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class YearInReviewRepositoryTest extends KernelTestCase
{
    public function testOnlyEligibleUnannouncedReviewsOfWillingOwnersAwaitTheirEmail(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var YearInReviewRepository $repository */
        $repository = static::getContainer()->get(YearInReviewRepository::class);

        $awaiting = $this->eligible($em, YearInReviewTestHelper::createVerifiedUser($em), 2026);
        $alreadySent = $this->eligible($em, YearInReviewTestHelper::createVerifiedUser($em), 2026);
        $alreadySent->emailedAt = new \DateTimeImmutable('2026-12-16 07:00:00');
        $optedOutOwner = YearInReviewTestHelper::createVerifiedUser($em);
        $optedOutOwner->emailOnYearInReview = false;
        $optedOut = $this->eligible($em, $optedOutOwner, 2026);
        $otherYear = $this->eligible($em, YearInReviewTestHelper::createVerifiedUser($em), 2027);
        $encouragement = YearInReview::notEligible(YearInReviewTestHelper::createVerifiedUser($em), 2026, 3);
        $em->persist($encouragement);
        $em->flush();

        $ids = $repository->findIdsAwaitingAnnouncement(2026);

        self::assertContains((string) $awaiting->id, $ids);
        self::assertNotContains((string) $alreadySent->id, $ids);
        self::assertNotContains((string) $optedOut->id, $ids);
        self::assertNotContains((string) $otherYear->id, $ids);
        self::assertNotContains((string) $encouragement->id, $ids);
    }

    private function eligible(EntityManagerInterface $em, User $owner, int $year): YearInReview
    {
        $review = YearInReview::eligible($owner, $year, new YearInReviewSnapshot(
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
