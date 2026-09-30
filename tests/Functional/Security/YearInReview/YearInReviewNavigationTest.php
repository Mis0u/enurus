<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\YearInReview;

use App\Entity\YearInReview;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

/**
 * Lien « Mes résumés » de la sidebar et de la tuile du panneau « Plus » (tous deux dans le DOM de
 * chaque page), et leur point bleu.
 */
final class YearInReviewNavigationTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string ANY_PAGE_URL = '/fr/mes-badges';

    private const string RECAP_LINK = 'a[href="/fr/mes-resumes"]';

    private const string PUBLISHED = '2026-12-16 00:00:00 Europe/Paris';

    public function testNoLinkBeforeThePublication(): void
    {
        self::mockTime('2026-12-15 23:59:59 Europe/Paris');

        self::assertCount(0, $this->pageFor(self::USER)->filter(self::RECAP_LINK));
    }

    public function testLinkForEveryoneFromThePublicationEvenWithoutReview(): void
    {
        self::mockTime(self::PUBLISHED);

        $crawler = $this->pageFor(self::USER);

        self::assertCount(2, $crawler->filter(self::RECAP_LINK));
        self::assertCount(0, $crawler->filter(self::RECAP_LINK . ' .bg-cyan-ft'));
    }

    public function testUnseenEligibleReviewLightsTheBlueDotOnTheLinkAndOnTheMoreButton(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::eligible($this->getUserByEmail(self::USER), 2026, $this->snapshot()));

        $crawler = $client->request(Request::METHOD_GET, self::ANY_PAGE_URL);

        self::assertCount(2, $crawler->filter(self::RECAP_LINK . ' .bg-cyan-ft'));
        self::assertCount(1, $crawler->filter('.more-notification-dot'));
    }

    public function testOpenedReviewNoLongerLightsTheDot(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $review = YearInReview::eligible($this->getUserByEmail(self::USER), 2026, $this->snapshot());
        $review->seenAt = new \DateTimeImmutable();
        $this->persist($review);

        $crawler = $client->request(Request::METHOD_GET, self::ANY_PAGE_URL);

        self::assertCount(0, $crawler->filter(self::RECAP_LINK . ' .bg-cyan-ft'));
    }

    public function testEncouragementNeverLightsTheDot(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::notEligible($this->getUserByEmail(self::USER), 2026, 3));

        $crawler = $client->request(Request::METHOD_GET, self::ANY_PAGE_URL);

        self::assertCount(0, $crawler->filter(self::RECAP_LINK . ' .bg-cyan-ft'));
    }

    private function pageFor(string $email): Crawler
    {
        return $this->login($email)->request(Request::METHOD_GET, self::ANY_PAGE_URL);
    }

    private function persist(YearInReview $review): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($review);
        $em->flush();
    }

    private function snapshot(): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals(12, 0, 0, 5000.0),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(1, 1, 12),
            [],
            null,
        );
    }
}
