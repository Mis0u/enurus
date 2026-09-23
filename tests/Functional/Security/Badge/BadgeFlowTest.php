<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Badge;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Entity\UserBadge;
use App\Entity\Workout;
use App\Enum\Badge\BadgeFamilyEnum;
use App\Enum\Badge\BadgeTierEnum;
use App\Enum\Dashboard\DashboardWidgetEnum;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
use App\Repository\ExerciseRepository;
use App\Repository\UserBadgeRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class BadgeFlowTest extends WebTestCase
{
    use FunctionalTestTrait;

    // Aucune séance en fixture : la première séance enregistrée débloque "1 séance".
    private const string NEW_USER = 'user-fixture-1@test.com';

    // 26 séances en fixture, aucun badge encore enregistré (première synchro = rattrapage).
    private const string EXISTING_USER = 'user-fixture-26-workout@test.com';

    private const string VIEWER = 'user-fixture-11-workout@test.com';

    private const string POPUP_SELECTOR = '[data-controller="badge--unlocked"]';

    public function testFirstWorkoutUnlocksTheFirstBadgeWithAPopup(): void
    {
        $client = $this->login(self::NEW_USER);
        $user = $this->freshlyRegistered(self::NEW_USER);

        $crawler = $this->logWorkoutAndFollow($client);

        self::assertCount(1, $crawler->filter(self::POPUP_SELECTOR));
        self::assertSame('Nouveau badge !', $crawler->filter(self::POPUP_SELECTOR)->attr('data-badge--unlocked-title-value'));
        self::assertSame('/fr/mes-badges', $crawler->filter(self::POPUP_SELECTOR)->attr('data-badge--unlocked-see-all-url-value'));

        $badges = $this->badgeRepository()->findByOwner($user);
        self::assertCount(1, $badges);
        self::assertSame(BadgeFamilyEnum::ASSIDUITY, $badges[0]->family);
        self::assertSame(BadgeTierEnum::BRONZE, $badges[0]->tier);
        self::assertSame($this->latestWorkoutOf($user), $badges[0]->workout);
    }

    public function testWorkoutPageShowsTheBadgeItUnlocked(): void
    {
        $client = $this->login(self::NEW_USER);
        $user = $this->freshlyRegistered(self::NEW_USER);
        $this->logWorkoutAndFollow($client);
        $workout = $this->latestWorkoutOf($user);

        $client->request(Request::METHOD_GET, '/fr/seance/' . $workout->id?->toRfc4122());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.show-header-card', 'Badge débloqué lors de cette séance');
        self::assertSelectorTextContains('.show-header-card', '1 séance');
    }

    public function testDeletingTheOnlyWorkoutRemovesTheBadgeSilently(): void
    {
        $client = $this->login(self::NEW_USER);
        $user = $this->freshlyRegistered(self::NEW_USER);
        $this->logWorkoutAndFollow($client);
        $workout = $this->latestWorkoutOf($user);
        $workoutId = $workout->id?->toRfc4122();

        $token = $this->csrfTokenFromPage($client, '/fr/mes-seances', \sprintf('button[data-delete-url*="%s"]', $workoutId), 'data-token');
        $this->deleteRequest($client, '/fr/seance/' . $workoutId . '/supprimer', $token);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->badgeRepository()->findByOwner($user));

        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        self::assertSelectorNotExists(self::POPUP_SELECTOR);
    }

    public function testFirstVisitOfAnExistingAccountShowsASingleRetroactivePopup(): void
    {
        $client = $this->login(self::EXISTING_USER);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertCount(1, $crawler->filter(self::POPUP_SELECTOR));
        self::assertSame('Tes badges sont arrivés !', $crawler->filter(self::POPUP_SELECTOR)->attr('data-badge--unlocked-title-value'));

        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        self::assertSelectorNotExists(self::POPUP_SELECTOR);
    }

    public function testRetroactiveBadgeIsDatedAndLinkedToTheWorkoutThatReallyEarnedIt(): void
    {
        $client = $this->login(self::EXISTING_USER);
        $user = $this->getUserByEmail(self::EXISTING_USER);

        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $firstWorkout = $workoutRepository->findOneBy([
            'owner' => $user,
        ], [
            'performedAt' => 'ASC',
            'id' => 'ASC',
        ]) ?? throw new \LogicException('No workout found for user.');

        $firstBadge = $this->badgeOf($user, BadgeFamilyEnum::ASSIDUITY, BadgeTierEnum::BRONZE);
        self::assertSame($firstWorkout->id?->toRfc4122(), $firstBadge->workout?->id?->toRfc4122());
        self::assertEquals($firstWorkout->performedAt, $firstBadge->unlockedAt);

        $client->request(Request::METHOD_GET, '/fr/seance/' . $firstWorkout->id?->toRfc4122());
        self::assertSelectorTextContains('.show-header-card', '1 séance');
    }

    public function testWorkoutListShowsBadgeMiniaturesOnTheWorkoutThatEarnedThem(): void
    {
        $client = $this->login(self::EXISTING_USER);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-seances?limit=50');

        self::assertGreaterThan(0, $crawler->filter('svg.badge-svg--xxs')->count());
        self::assertGreaterThan(0, $crawler->filter('[title*="1 séance"]')->count());
    }

    public function testDashboardWidgetLinksToTheBadgesPage(): void
    {
        $client = $this->login(self::EXISTING_USER);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorTextContains('body', 'Prochains paliers');
        self::assertCount(1, $crawler->filter('main a[href="/fr/mes-badges"]'));
        // Plus de section "Tous mes badges" sur le dashboard : la page dédiée la remplace.
        self::assertSelectorNotExists('details#badges');
    }

    public function testSidebarLinksToTheBadgesPage(): void
    {
        $client = $this->login(self::EXISTING_USER);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertGreaterThan(0, $crawler->filter('nav a[href="/fr/mes-badges"]')->count());
    }

    public function testBadgesPageListsEveryBadge(): void
    {
        $client = $this->login(self::EXISTING_USER);

        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-badges');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('title', 'Mes badges');
        // 4 familles × 6 paliers + la Légende.
        self::assertCount(25, $crawler->filter('main section svg.badge-svg'));
    }

    public function testBadgesPageStaysReachableWhenTheWidgetIsHidden(): void
    {
        $client = $this->login(self::EXISTING_USER);
        $user = $this->getUserByEmail(self::EXISTING_USER);
        $user->hiddenWidgets = [DashboardWidgetEnum::BADGES->value];
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        self::assertSelectorNotExists('main a[href="/fr/mes-badges"]');

        $client->request(Request::METHOD_GET, '/fr/mes-badges');
        self::assertResponseIsSuccessful();
    }

    public function testBadgesPageRequiresLogin(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged('/fr/mes-badges');
    }

    public function testConnectionSeesTheBadgesPageWithoutAnyPopup(): void
    {
        $client = $this->login(self::VIEWER);
        $connection = $this->acceptedConnection();

        $client->request(Request::METHOD_GET, $this->badgePageUrl($connection));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('section h2', 'Badges de');
        // La synchro silencieuse du propriétaire ne doit jamais produire de popup chez le visiteur.
        self::assertSelectorNotExists(self::POPUP_SELECTOR);
        self::assertNotSame([], $this->badgeRepository()->findByOwner($this->getUserByEmail(self::EXISTING_USER)));
    }

    public function testSharedDashboardWidgetLinksToTheConnectionBadgesPage(): void
    {
        $client = $this->login(self::VIEWER);
        $connection = $this->acceptedConnection();

        $crawler = $client->request(Request::METHOD_GET, '/fr/connexions/' . $connection->id?->toRfc4122() . '/tableau-de-bord');

        $links = $crawler->filter(\sprintf('a[href="%s"]', $this->badgePageUrl($connection)));
        self::assertCount(1, $links);
        self::assertStringContainsString('Voir tous ses badges', $links->text());
    }

    public function testBadgesPageIsForbiddenWhenTheOwnerHidesTheWidgetFromConnections(): void
    {
        $client = $this->login(self::VIEWER);
        $connection = $this->acceptedConnection();
        $this->getUserByEmail(self::EXISTING_USER)->hiddenSharedWidgets = [DashboardWidgetEnum::BADGES->value];
        $this->entityManager()->flush();

        $client->request(Request::METHOD_GET, $this->badgePageUrl($connection));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testBadgesPageIsForbiddenForAPendingConnection(): void
    {
        $client = $this->login(self::VIEWER);
        $connection = $this->acceptedConnection(ProfileConnectionStatusEnum::PENDING);

        $client->request(Request::METHOD_GET, $this->badgePageUrl($connection));

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    /**
     * Les comptes fixtures sont créés au chargement des fixtures : selon l'ancienneté de la base
     * de test, ils pourraient déjà mériter un badge d'ancienneté. On les rajeunit pour que le test
     * ne dépende jamais de la date.
     */
    private function freshlyRegistered(string $email): User
    {
        $user = $this->getUserByEmail($email);
        $user->createdAt = new \DateTimeImmutable();
        $this->entityManager()->flush();

        return $user;
    }

    private function logWorkoutAndFollow(KernelBrowser $client): Crawler
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        WorkoutTestHelper::submitWorkout($client, $exerciseRepository);
        self::assertResponseRedirects();

        return $client->followRedirect();
    }

    private function latestWorkoutOf(User $user): Workout
    {
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        return $workoutRepository->findOneBy([
            'owner' => $user,
        ], [
            'id' => 'DESC',
        ]) ?? throw new \LogicException('No workout found for user.');
    }

    private function acceptedConnection(ProfileConnectionStatusEnum $status = ProfileConnectionStatusEnum::ACCEPTED): ProfileConnection
    {
        $viewer = $this->getUserByEmail(self::VIEWER);
        $owner = $this->getUserByEmail(self::EXISTING_USER);
        $viewer->isDiscoverable = true;
        $owner->isDiscoverable = true;

        $connection = new ProfileConnection();
        $connection->requester = $viewer;
        $connection->addressee = $owner;
        $connection->status = $status;

        $this->entityManager()->persist($connection);
        $this->entityManager()->flush();

        return $connection;
    }

    private function badgePageUrl(ProfileConnection $connection): string
    {
        return '/fr/connexions/' . $connection->id?->toRfc4122() . '/badges';
    }

    private function badgeOf(User $user, BadgeFamilyEnum $family, BadgeTierEnum $tier): UserBadge
    {
        foreach ($this->badgeRepository()->findByOwner($user) as $badge) {
            if ($badge->family === $family && $badge->tier === $tier) {
                return $badge;
            }
        }

        throw new \LogicException('Badge not found.');
    }

    private function badgeRepository(): UserBadgeRepository
    {
        /** @var UserBadgeRepository $repository */
        $repository = static::getContainer()->get(UserBadgeRepository::class);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
