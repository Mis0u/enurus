<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Enum\Entity\Workout\WorkoutMoodEnum;
use App\Enum\YearInReview\YearInReviewScreenEnum;
use App\Repository\MuscleGroupRepository;
use App\Service\YearInReview\Snapshot\YearInReviewBadge;
use App\Service\YearInReview\Snapshot\YearInReviewExercise;
use App\Service\YearInReview\Snapshot\YearInReviewHeaviestSet;
use App\Service\YearInReview\Snapshot\YearInReviewMood;
use App\Service\YearInReview\Snapshot\YearInReviewMuscle;
use App\Service\YearInReview\Snapshot\YearInReviewRecords;
use App\Service\YearInReview\Snapshot\YearInReviewRegularity;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use App\Service\YearInReview\Snapshot\YearInReviewTotals;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class YearInReviewShowControllerTest extends WebTestCase
{
    use ClockSensitiveTrait;
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string SHOW_URL = '/fr/mes-resumes/2026';

    private const string PUBLISHED = '2026-12-16 00:00:00 Europe/Paris';

    public function testEveryScreenOfAFullYearIsRendered(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::eligible($this->user(), 2026, $this->fullSnapshot()));

        $crawler = $client->request(Request::METHOD_GET, self::SHOW_URL);

        self::assertResponseIsSuccessful();
        self::assertCount(\count(YearInReviewScreenEnum::cases()), $crawler->filter('[data-year-in-review-screen]'));
    }

    public function testFirstOpeningIsRecordedOnlyOnce(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $review = YearInReview::eligible($this->user(), 2026, $this->fullSnapshot());
        $this->persist($review);

        $client->request(Request::METHOD_GET, self::SHOW_URL);
        $firstSeenAt = $review->seenAt;
        self::mockTime('2026-12-20 10:00:00 Europe/Paris');
        $client->request(Request::METHOD_GET, self::SHOW_URL);

        self::assertNotNull($firstSeenAt);
        self::assertEquals($firstSeenAt, $this->reloaded($review)->seenAt);
    }

    public function testReadyCardOfTheListOpensTheScreens(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::eligible($this->user(), 2026, $this->fullSnapshot()));

        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-resumes');

        self::assertCount(1, $crawler->filter(\sprintf('a[data-year-in-review-card="ready"][href="%s"]', self::SHOW_URL)));
    }

    public function testDoesNotExistBeforePublication(): void
    {
        self::mockTime('2026-12-15 23:59:59 Europe/Paris');
        $client = $this->login(self::USER);
        $this->persist(YearInReview::eligible($this->user(), 2026, $this->fullSnapshot()));

        $client->request(Request::METHOD_GET, self::SHOW_URL);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testYearWithoutReviewDoesNotExist(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_GET, self::SHOW_URL);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testReviewBelowTheThresholdDoesNotExist(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::notEligible($this->user(), 2026, 3));

        $client->request(Request::METHOD_GET, self::SHOW_URL);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnotherUserCannotOpenIt(): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login('user-fixture-26-workout@test.com');
        $this->persist(YearInReview::eligible($this->user(), 2026, $this->fullSnapshot()));

        $client->request(Request::METHOD_GET, self::SHOW_URL);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        self::mockTime(self::PUBLISHED);

        $this->assertPageIsRedirectToLoginWhenNotLogged(self::SHOW_URL);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function showUrlPerLocale(): iterable
    {
        yield 'fr' => ['/fr/mes-resumes/2026'];
        yield 'en' => ['/en/my-recaps/2026'];
        yield 'it' => ['/it/i-miei-riepiloghi/2026'];
        yield 'es' => ['/es/mis-resumenes/2026'];
        yield 'pt' => ['/pt/meus-resumos/2026'];
        yield 'de' => ['/de/meine-rueckblicke/2026'];
        yield 'nl' => ['/nl/mijn-overzichten/2026'];
        yield 'pl' => ['/pl/moje-podsumowania/2026'];
    }

    #[DataProvider('showUrlPerLocale')]
    public function testEveryLanguageShowsTranslatedTextInsteadOfRawKeys(string $url): void
    {
        self::mockTime(self::PUBLISHED);
        $client = $this->login(self::USER);
        $this->persist(YearInReview::eligible($this->user(), 2026, $this->fullSnapshot()));

        $crawler = $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('year_in_review.', $crawler->filter('body')->text());
        self::assertStringNotContainsString('.name', $crawler->filter('body')->text());
        self::assertStringNotContainsString('name.', $crawler->filter('body')->text());
    }

    private function user(): User
    {
        return $this->getUserByEmail(self::USER);
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    private function persist(YearInReview $review): void
    {
        $this->entityManager()->persist($review);
        $this->entityManager()->flush();
    }

    private function reloaded(YearInReview $review): YearInReview
    {
        $this->entityManager()->clear();

        return $this->entityManager()->find(YearInReview::class, $review->id) ?? throw new \LogicException('Review vanished.');
    }

    private function fullSnapshot(): YearInReviewSnapshot
    {
        return new YearInReviewSnapshot(
            new YearInReviewTotals(139, 2028, 16224, 312000.0),
            [
                '2026-01-05' => 4200.0,
                '2026-01-07' => 3875.5,
                '2026-01-09' => 5100.0,
            ],
            [new YearInReviewExercise('bench_press_bar.name', true, 94), new YearInReviewExercise('Mon squat perso', false, 49)],
            new YearInReviewMuscle($this->anyMuscleGroupId(), 572),
            new YearInReviewRecords(75, new YearInReviewHeaviestSet('deadlift.name', true, 172.5, new \DateTimeImmutable('2026-11-25'))),
            new YearInReviewRegularity(15, 3, 17),
            [new YearInReviewBadge(BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::GOLD), new YearInReviewBadge(BadgeFamilyEnum::TONNAGE, BadgeTierEnum::SILVER)],
            new YearInReviewMood(WorkoutMoodEnum::EN_FORME, 50),
        );
    }

    private function anyMuscleGroupId(): string
    {
        /** @var MuscleGroupRepository $muscleGroupRepository */
        $muscleGroupRepository = static::getContainer()->get(MuscleGroupRepository::class);
        $muscleGroup = $muscleGroupRepository->findOneBy([]) ?? throw new \LogicException('No muscle group in fixtures.');

        return (string) $muscleGroup->id;
    }
}
