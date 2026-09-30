<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Service\Email\EmailInterface;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Service\YearInReview\YearInReviewAnnouncer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Contracts\Translation\TranslatorInterface;

final class YearInReviewAnnouncerTest extends TestCase
{
    private const string NOW = '2026-12-16 07:00:00';

    public function testEligibleReviewIsAnnouncedOnceInTheOwnerLanguage(): void
    {
        $review = $this->eligibleReview($this->owner());
        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::once())
            ->method('createEmail')
            ->with('owner@test.com', 'subject', [
                'year' => 2026,
                'locale' => 'de',
            ], 'emails/year_in_review_announcement.html.twig', 'de')
            ->willReturn(new TemplatedEmail());
        $emailService->expects(self::once())->method('sendEmail');

        $this->announcer($emailService)->announce($review);

        self::assertEquals(new \DateTimeImmutable(self::NOW), $review->emailedAt);
    }

    public function testAlreadyAnnouncedReviewIsNotSentAgain(): void
    {
        $review = $this->eligibleReview($this->owner());
        $review->emailedAt = new \DateTimeImmutable('2026-12-16 07:00:01');

        $this->announcer($this->neverSending())->announce($review);
    }

    public function testOwnerWhoTurnedTheEmailOffGetsNothing(): void
    {
        $owner = $this->owner();
        $owner->emailOnYearInReview = false;

        $this->announcer($this->neverSending())->announce($this->eligibleReview($owner));
    }

    public function testEncouragementIsNeverEmailed(): void
    {
        $this->announcer($this->neverSending())->announce(YearInReview::notEligible($this->owner(), 2026, 3));
    }

    public function testLeavingAccountGetsNothing(): void
    {
        $owner = $this->owner();
        $owner->deletionRequestedAt = new \DateTimeImmutable('2026-12-01');

        $this->announcer($this->neverSending())->announce($this->eligibleReview($owner));
    }

    public function testFailedSendingIsLoggedAndRetriedLater(): void
    {
        $review = $this->eligibleReview($this->owner());
        $emailService = $this->createStub(EmailInterface::class);
        $emailService->method('createEmail')->willReturn(new TemplatedEmail());
        $emailService->method('sendEmail')->willThrowException(new TransportException('down'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $this->announcer($emailService, $logger)->announce($review);

        self::assertNull($review->emailedAt);
    }

    private function announcer(EmailInterface $emailService, ?LoggerInterface $logger = null): YearInReviewAnnouncer
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('subject');

        return new YearInReviewAnnouncer(
            $emailService,
            $translator,
            $logger ?? $this->createStub(LoggerInterface::class),
            $this->createStub(EntityManagerInterface::class),
            new MockClock(self::NOW),
        );
    }

    private function neverSending(): EmailInterface
    {
        $emailService = $this->createMock(EmailInterface::class);
        $emailService->expects(self::never())->method('sendEmail');

        return $emailService;
    }

    private function owner(): User
    {
        $owner = new User();
        $owner->email = 'owner@test.com';
        $owner->locale = 'de';
        $owner->isVerified = true;

        return $owner;
    }

    private function eligibleReview(User $owner): YearInReview
    {
        return YearInReview::eligible($owner, 2026, new YearInReviewSnapshot(
            new YearInReviewTotals(12, 36, 360, 5400.0),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(2, 3, 5),
            [],
            null,
        ));
    }
}
