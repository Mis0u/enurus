<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\ProfileConnection;

use App\Entity\ExerciseGoal;
use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class ProfileConnectionDashboardControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string VIEWER = 'user-fixture-11-workout@test.com';

    // 26 séances, en kg : Régularité et muscles semaine/mois débloqués.
    private const string SUBJECT_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    private const string SUBJECT_WITH_NO_WORKOUT = 'user-fixture-0@test.com';

    // 51 séances, en lbs.
    private const string LBS_VIEWER = 'user-fixture-51-workout@test.com';

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $connection = $this->createConnection(
            $this->getUserByEmail(self::VIEWER),
            $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS),
            ProfileConnectionStatusEnum::ACCEPTED,
        );

        $this->assertPageIsRedirectToLoginWhenNotLogged($this->dashboardUrl($connection, 'fr'), $client);
    }

    public function testUnknownConnectionIsNotFound(): void
    {
        $client = $this->login(self::VIEWER);

        $client->request('GET', '/fr/connexions/' . Uuid::v7()->toRfc4122() . '/tableau-de-bord');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRequesterSeesTheDashboardOfTheAddresseeInReadOnly(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tableau de bord de ' . $subject->nickname);
        self::assertSelectorTextContains('main', 'Lecture seule');
        self::assertSelectorExists('[data-controller="dashboard--session"]');
    }

    public function testAddresseeSeesTheDashboardOfTheRequesterToo(): void
    {
        $client = $this->login(self::VIEWER);
        $requester = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $connection = $this->createConnection($requester, $this->getUserByEmail(self::VIEWER), ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Tableau de bord de ' . $requester->nickname);
    }

    public function testPageFollowsTheLanguageOfTheViewer(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'en'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', $subject->nickname . "'s dashboard");
        self::assertSelectorTextContains('main', 'Read only');
    }

    /**
     * @return iterable<string, array{ProfileConnectionStatusEnum}>
     */
    public static function notAcceptedStatuses(): iterable
    {
        yield 'pending' => [ProfileConnectionStatusEnum::PENDING];
        yield 'declined' => [ProfileConnectionStatusEnum::DECLINED];
        yield 'revoked' => [ProfileConnectionStatusEnum::REVOKED];
    }

    #[DataProvider('notAcceptedStatuses')]
    public function testDashboardIsForbiddenUntilTheConnectionIsAccepted(ProfileConnectionStatusEnum $status): void
    {
        $client = $this->login(self::VIEWER);
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS), $status);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseStatusCodeSame(403);
    }

    public function testAStrangerCannotSeeTheDashboardOfAnAcceptedConnection(): void
    {
        $client = $this->login(self::LBS_VIEWER);
        $connection = $this->createConnection(
            $this->getUserByEmail(self::VIEWER),
            $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS),
            ProfileConnectionStatusEnum::ACCEPTED,
        );

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseStatusCodeSame(403);
    }

    public function testOwnerWithoutAnyWorkoutShowsADedicatedMessageInsteadOfTheOwnInvitation(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_NO_WORKOUT);
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $subject->nickname . " n'a pas encore créé de séance");
        self::assertSelectorTextNotContains('main', 'commence ta première séance');
        self::assertSelectorNotExists('[data-controller="dashboard--session"]');
    }

    public function testOwnerWhoHidEveryWidgetShowsANeutralMessageWithoutLinkToTheSettings(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $subject->hiddenWidgets = array_map(static fn (DashboardWidgetEnum $widget): string => $widget->value, DashboardWidgetEnum::cases());
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', $subject->nickname . ' a décidé de ne pas afficher de data');
        self::assertSelectorNotExists('a[href*="#dashboard-widgets"]');
        self::assertSelectorNotExists('[data-controller="dashboard--session"]');
    }

    public function testOnlyTheWidgetsTheOwnerKeptVisibleAreShown(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $subject->hiddenWidgets = [DashboardWidgetEnum::TONNAGE->value];
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-controller="dashboard--tonnage"]');
        self::assertSelectorExists('[data-controller="dashboard--session"]');
    }

    public function testWeightUnitIsTheViewersNotTheOwners(): void
    {
        $client = $this->login(self::LBS_VIEWER);
        self::assertSame(
            UnitOfMeasureEnum::KG,
            $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS)->unitOfMeasure,
            'Le fixture propriétaire doit être en kg pour que ce test discrimine.',
        );
        $connection = $this->createConnection($this->getUserByEmail(self::LBS_VIEWER), $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS), ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-controller="dashboard--tonnage"]', 'lbs');
    }

    public function testOwnersAvatarIsShownInTheBanner(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $subject->avatarPath = 'avatars/owner-avatar.jpg';
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('main img[src*="avatars/owner-avatar.jpg"]');
    }

    public function testGoalCardsOfTheOwnerAreNotLinksToAnExerciseHistory(): void
    {
        $client = $this->login(self::VIEWER);
        $subject = $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS);
        $this->createGoal($subject);
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $subject, ProfileConnectionStatusEnum::ACCEPTED);

        $crawler = $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-controller="dashboard--goal"]');
        self::assertCount(0, $crawler->filter('[data-controller="dashboard--goal"] a'));
    }

    public function testOwnDashboardKeepsGoalCardsAsLinksToTheExerciseHistory(): void
    {
        $client = $this->login(self::SUBJECT_WITH_WORKOUTS);
        $this->createGoal($this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS));

        $crawler = $client->request('GET', '/fr/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertGreaterThanOrEqual(1, $crawler->filter('[data-controller="dashboard--goal"] a[href*="historique"]')->count());
    }

    public function testViewersOwnNavigationIsStillThere(): void
    {
        $client = $this->login(self::VIEWER);
        $connection = $this->createConnection($this->getUserByEmail(self::VIEWER), $this->getUserByEmail(self::SUBJECT_WITH_WORKOUTS), ProfileConnectionStatusEnum::ACCEPTED);

        $client->request('GET', $this->dashboardUrl($connection, 'fr'));

        self::assertSelectorExists('a[href*="/messagerie"]');
    }

    private function dashboardUrl(ProfileConnection $connection, string $locale): string
    {
        $segments = [
            'fr' => '/fr/connexions/%s/tableau-de-bord',
            'en' => '/en/connections/%s/dashboard',
        ];

        return \sprintf($segments[$locale], $connection->id?->toRfc4122());
    }

    private function createConnection(User $requester, User $addressee, ProfileConnectionStatusEnum $status): ProfileConnection
    {
        $entityManager = $this->entityManager();

        $connection = new ProfileConnection();
        $connection->requester = $requester;
        $connection->addressee = $addressee;
        $connection->status = $status;

        $entityManager->persist($connection);
        $entityManager->flush();

        return $connection;
    }

    private function createGoal(User $owner): void
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        foreach ($exerciseRepository->findPublicExercises() as $exercise) {
            if (MeasurementType::WEIGHT_REPS !== $exercise->measurementType) {
                continue;
            }

            $goal = ExerciseGoal::draftFor($owner, $exercise);
            $goal->targetWeight = 100.0;

            $this->entityManager()->persist($goal);
            $this->entityManager()->flush();

            return;
        }

        throw new \LogicException('Expected a public weight/reps exercise in the fixtures.');
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
