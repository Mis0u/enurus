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
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class YearInReviewBannerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string DASHBOARD_URL = '/fr/tableau-de-bord';

    private const string DISMISS_URL = '/fr/mes-resumes/2026/bandeau';

    private const string BANNER = '[data-controller="year-in-review--banner"]';

    private const string TOKEN_ATTRIBUTE = 'data-year-in-review--banner-csrf-token-value';

    public function testUnopenedEligibleReviewIsAnnouncedAtTheTopOfTheDashboard(): void
    {
        self::mockTime('2026-12-16 00:00:00 Europe/Paris');
        $client = $this->login(self::USER);
        $this->persistEligibleReview();

        $crawler = $client->request(Request::METHOD_GET, self::DASHBOARD_URL);

        self::assertCount(1, $crawler->filter(self::BANNER . ' a[href="/fr/mes-resumes/2026"]'));
    }

    public function testNoBannerOnceFebruaryHasStarted(): void
    {
        self::mockTime('2027-02-01 00:00:00 Europe/Paris');
        $client = $this->login(self::USER);
        $this->persistEligibleReview();

        $crawler = $client->request(Request::METHOD_GET, self::DASHBOARD_URL);

        self::assertCount(0, $crawler->filter(self::BANNER));
    }

    public function testNoBannerOnceTheReviewWasOpened(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $client = $this->login(self::USER);
        $this->persistEligibleReview();

        $client->request(Request::METHOD_GET, '/fr/mes-resumes/2026');
        $crawler = $client->request(Request::METHOD_GET, self::DASHBOARD_URL);

        self::assertCount(0, $crawler->filter(self::BANNER));
    }

    public function testDismissedBannerNeverComesBack(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $client = $this->login(self::USER);
        $review = $this->persistEligibleReview();

        $this->dismiss($client, $this->tokenFor($client));

        self::assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);
        self::assertNotNull($this->reloaded($review)->bannerDismissedAt);
        self::assertCount(0, $client->request(Request::METHOD_GET, self::DASHBOARD_URL)->filter(self::BANNER));
    }

    public function testDismissRejectsAnInvalidCsrfToken(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $client = $this->login(self::USER);
        $review = $this->persistEligibleReview();

        $this->dismiss($client, 'invalid');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertNull($this->reloaded($review)->bannerDismissedAt);
    }

    public function testDismissOutsideXhrIsRefused(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_POST, self::DISMISS_URL);

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testDismissWithoutReviewDoesNotExist(): void
    {
        self::mockTime('2026-12-17 10:00:00 Europe/Paris');
        $client = $this->login(self::USER);
        $token = $this->tokenForAnyReview($client);
        $this->entityManager()->createQuery('DELETE FROM ' . YearInReview::class . ' r')->execute();

        $this->dismiss($client, $token);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function dismiss(KernelBrowser $client, string $token): void
    {
        $client->request(Request::METHOD_POST, self::DISMISS_URL, server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_X-CSRF-Token' => $token,
        ]);
    }

    private function tokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage($client, self::DASHBOARD_URL, self::BANNER, self::TOKEN_ATTRIBUTE);
    }

    private function tokenForAnyReview(KernelBrowser $client): string
    {
        $this->persistEligibleReview();

        return $this->tokenFor($client);
    }

    private function reloaded(YearInReview $review): YearInReview
    {
        $this->entityManager()->clear();

        return $this->entityManager()->find(YearInReview::class, $review->id) ?? throw new \LogicException('Review vanished.');
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persistEligibleReview(): YearInReview
    {
        $review = YearInReview::eligible($this->getUserByEmail(self::USER), 2026, new YearInReviewSnapshot(
            new YearInReviewTotals(12, 36, 360, 5400.0),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(2, 3, 5),
            [],
            null,
        ));
        $this->entityManager()->persist($review);
        $this->entityManager()->flush();

        return $review;
    }
}
