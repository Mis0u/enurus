<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\YearInReview;

use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Entity\YearInReview;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class YearInReviewListControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string LIST_URL = '/fr/mes-resumes';

    private const string TOGGLE_URL = '/fr/mes-resumes/email';

    private const string PUBLISHED = '2026-12-16 00:00:00 Europe/Paris';

    private const string TOKEN_ATTRIBUTE = 'data-email-notification-toggle-csrf-token-value';

    /**
     * @return iterable<string, array{string}>
     */
    public static function momentsBeforePublication(): iterable
    {
        yield 'mid-November' => ['2026-11-10 12:00:00 Europe/Paris'];
        yield 'last second of the 15th' => ['2026-12-15 23:59:59 Europe/Paris'];
    }

    #[DataProvider('momentsBeforePublication')]
    public function testPageDoesNotExistBeforePublication(string $now): void
    {
        self::mockTime($now);
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testPageOpensAtMidnightOfTheSixteenth(): void
    {
        self::mockTime(self::PUBLISHED);

        $this->assertPageIsAccessibleWhenLogged(self::USER, self::LIST_URL, 'Mes résumés | Enurus');
    }

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        self::mockTime(self::PUBLISHED);

        $this->assertPageIsRedirectToLoginWhenNotLogged(self::LIST_URL);
    }

    public function testEligibleYearShowsItsFrozenFigures(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::eligible($this->user(), 2026, $this->snapshot(workoutCount: 139, tonnageKg: 1292934.4)));

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        $card = $crawler->filter('[data-year-in-review-card="ready"]');
        self::assertCount(1, $card);
        self::assertStringContainsString('2026', $card->text());
        self::assertStringContainsString('139 séances', $card->text());
    }

    public function testYearBelowTheThresholdShowsTheEncouragement(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::notEligible($this->user(), 2026, 3));

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        $card = $crawler->filter('[data-year-in-review-card="not_enough"]');
        self::assertCount(1, $card);
        self::assertStringContainsString('3 séances cette année : encore 2 et tu débloquais ton résumé !', $card->text());
    }

    public function testYearNotGeneratedYetIsAnnouncedAsInPreparation(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertCount(1, $crawler->filter('[data-year-in-review-card="pending"]'));
    }

    public function testEquivalenceScaleIsForTheAdminOnlyAndFolded(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(UserFixtures::USER_ADMIN);

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertCount(1, $crawler->filter('details[data-equivalence-scale]:not([open])'));
    }

    public function testEquivalenceScaleIsHiddenFromUsers(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertCount(0, $crawler->filter('[data-equivalence-scale]'));
    }

    public function testEmailIsOnByDefaultAndCanBeTurnedOff(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);
        self::assertCount(1, $crawler->filter('#email input[type="checkbox"][checked]'));

        $this->sendToggle($client, false, $this->tokenFor($client));

        self::assertResponseIsSuccessful();
        self::assertFalse($this->getUserByEmail(self::USER)->emailOnYearInReview);
    }

    public function testToggleRejectsAnInvalidCsrfToken(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);

        $this->sendToggle($client, false, 'invalid');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertTrue($this->getUserByEmail(self::USER)->emailOnYearInReview);
    }

    public function testToggleDoesNotExistBeforePublication(): void
    {
        self::mockTime('2026-11-10 12:00:00 Europe/Paris');
        $client = $this->login(self::USER);

        $this->sendToggle($client, false, 'any');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    private function sendToggle(KernelBrowser $client, bool $enabled, string $token): void
    {
        $client->request(
            Request::METHOD_PATCH,
            self::TOGGLE_URL,
            server: [
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $this->toJson([
                'enabled' => $enabled,
                '_token' => $token,
            ]),
        );
    }

    private function tokenFor(KernelBrowser $client): string
    {
        return $this->csrfTokenFromPage($client, self::LIST_URL, '[' . self::TOKEN_ATTRIBUTE . ']', self::TOKEN_ATTRIBUTE);
    }

    private function user(): User
    {
        return $this->getUserByEmail(self::USER);
    }

    private function persist(YearInReview $review): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($review);
        $em->flush();
    }

    private function snapshot(int $workoutCount, float $tonnageKg): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals($workoutCount, 0, 0, $tonnageKg),
            [],
            [],
            null,
            new YearInReviewRecords(0, null),
            new YearInReviewRegularity(1, 1, $workoutCount),
            [],
            null,
        );
    }
}
