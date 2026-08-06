<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Entity\User;
use App\Entity\Workout;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class DashboardControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    /**
     * @var list<User>
     */
    private array $createdUsers = [];

    /**
     * @var list<Workout>
     */
    private array $createdWorkouts = [];

    protected function tearDown(): void
    {
        // Les workouts d'abord — FK owner_id -> users sans ON DELETE CASCADE.
        foreach ($this->createdWorkouts as $workout) {
            $this->deleteTestWorkout($workout);
        }

        foreach ($this->createdUsers as $user) {
            $this->deleteTestUser($user);
        }

        $this->createdUsers = [];
        $this->createdWorkouts = [];

        parent::tearDown();
    }

    public function testMenuLabelIsRenamedToDashboard(): void
    {
        $client = $this->login(self::ADMIN);
        $client->request(Request::METHOD_GET, '/admin');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a[href$="/admin"] .menu-item-label', 'Tableau de bord');
        self::assertSelectorTextNotContains('a[href$="/admin"] .menu-item-label', 'Accueil');
    }

    public function testStatTilesReflectRecentRegistrations(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $before = $this->statTileValues($crawler);

        $this->createTestUser('dashboard-stat-today@test.com', GenderEnum::MALE, UnitOfMeasureEnum::KG, 'fr', new \DateTimeImmutable());

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $after = $this->statTileValues($crawler);

        self::assertSame($before['total'] + 1, $after['total']);
        self::assertSame($before['today'] + 1, $after['today']);
        self::assertSame($before['week'] + 1, $after['week']);
        self::assertSame($before['month'] + 1, $after['month']);
    }

    public function testPendingDeletionsStatTileReflectsDeletionRequest(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $before = $this->statTileValues($crawler);

        $user = $this->createTestUser('dashboard-pending-deletion@test.com', GenderEnum::MALE, UnitOfMeasureEnum::KG, 'fr', new \DateTimeImmutable());
        $this->requestDeletion($user);

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $after = $this->statTileValues($crawler);

        self::assertSame($before['pendingDeletions'] + 1, $after['pendingDeletions']);
    }

    public function testWorkoutsLast30DaysStatTileReflectsRecentWorkout(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $before = $this->statTileValues($crawler);

        $user = $this->createTestUser('dashboard-workout-30d@test.com', GenderEnum::MALE, UnitOfMeasureEnum::KG, 'fr', new \DateTimeImmutable());
        $this->createTestWorkout($user, new \DateTimeImmutable());

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $after = $this->statTileValues($crawler);

        self::assertSame($before['workouts'] + 1, $after['workouts']);
    }

    public function testLocaleChartCountsCreatedUser(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $before = $this->chartValueByLabel($crawler, 1, 'PL');

        $this->createTestUser('dashboard-locale-pl@test.com', GenderEnum::MALE, UnitOfMeasureEnum::KG, 'pl', new \DateTimeImmutable());

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $after = $this->chartValueByLabel($crawler, 1, 'PL');

        self::assertGreaterThan($before, $after);
    }

    public function testUnitOfMeasureChartCountsCreatedUser(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $before = $this->chartValueByLabel($crawler, 2, 'LBS');

        $this->createTestUser('dashboard-unit-lbs@test.com', GenderEnum::MALE, UnitOfMeasureEnum::LBS, 'fr', new \DateTimeImmutable());

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $after = $this->chartValueByLabel($crawler, 2, 'LBS');

        self::assertGreaterThan($before, $after);
    }

    public function testGenderChartCountsCreatedUser(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $before = $this->chartValueByLabel($crawler, 3, 'Femme');

        $this->createTestUser('dashboard-gender-female@test.com', GenderEnum::FEMALE, UnitOfMeasureEnum::KG, 'fr', new \DateTimeImmutable());

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $after = $this->chartValueByLabel($crawler, 3, 'Femme');

        self::assertGreaterThan($before, $after);
    }

    public function testRegistrationTrendChartCountsCreatedUserInCurrentWeek(): void
    {
        $client = $this->login(self::ADMIN);
        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $canvas = $crawler->filter('canvas')->eq(0);
        $raw = $canvas->attr('data-symfony--ux-chartjs--chart-view-value');
        self::assertIsString($raw);
        /** @var array{data: array{datasets: list<array{data: list<int>}>}} $before */
        $before = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $lastWeekValueBefore = end($before['data']['datasets'][0]['data']);

        $this->createTestUser('dashboard-trend@test.com', GenderEnum::MALE, UnitOfMeasureEnum::KG, 'fr', new \DateTimeImmutable());

        $crawler = $client->request(Request::METHOD_GET, '/admin');
        $canvas = $crawler->filter('canvas')->eq(0);
        $raw = $canvas->attr('data-symfony--ux-chartjs--chart-view-value');
        self::assertIsString($raw);
        /** @var array{data: array{datasets: list<array{data: list<int>}>}} $after */
        $after = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        $lastWeekValueAfter = end($after['data']['datasets'][0]['data']);

        self::assertSame($lastWeekValueBefore + 1, $lastWeekValueAfter);
    }

    /**
     * @return array{total: int, today: int, week: int, month: int, pendingDeletions: int, workouts: int}
     */
    private function statTileValues(Crawler $crawler): array
    {
        $values = $crawler->filter('.admin-stat-tile-value')->each(static fn (Crawler $node): int => (int) trim($node->text()));

        return [
            'total' => $values[0],
            'today' => $values[1],
            'week' => $values[2],
            'month' => $values[3],
            'pendingDeletions' => $values[4],
            'workouts' => $values[5],
        ];
    }

    /**
     * Lit le JSON embarqué par `render_chart()` (ux-chartjs) sur le canvas d'indice `$chartIndex`
     * de la page (0 = tendance inscriptions, 1 = locale, 2 = unité, 3 = genre) et retourne la
     * valeur (un pourcentage, sauf pour la tendance) associée au label donné.
     */
    private function chartValueByLabel(Crawler $crawler, int $chartIndex, string $label): float
    {
        $canvas = $crawler->filter('canvas')->eq($chartIndex);
        $raw = $canvas->attr('data-symfony--ux-chartjs--chart-view-value');

        self::assertIsString($raw);

        /** @var array{data: array{labels: list<string>, datasets: list<array{data: list<float>}>}} $view */
        $view = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        $index = array_search($label, $view['data']['labels'], true);
        self::assertIsInt($index);

        return $view['data']['datasets'][0]['data'][$index];
    }

    private function createTestUser(
        string $email,
        GenderEnum $gender,
        UnitOfMeasureEnum $unitOfMeasure,
        string $locale,
        \DateTimeImmutable $createdAt,
    ): User {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = new User();
        $user->email = $email;
        $user->password = 'hashed';
        $user->nickname = 'T' . substr(bin2hex(random_bytes(8)), 0, 16);
        $user->lastLogin = new \DateTimeImmutable();
        $user->locale = $locale;
        $user->gender = $gender;
        $user->unitOfMeasure = $unitOfMeasure;

        $entityManager->persist($user);
        $entityManager->flush();

        $user->createdAt = $createdAt;
        $entityManager->flush();

        $this->createdUsers[] = $user;

        return $user;
    }

    private function deleteTestUser(User $user): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $reloaded = $entityManager->getRepository(User::class)->find($user->id);

        if ($reloaded instanceof User) {
            $entityManager->remove($reloaded);
            $entityManager->flush();
        }
    }

    private function requestDeletion(User $user): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user->deletionRequestedAt = new \DateTimeImmutable();
        $entityManager->flush();
    }

    private function createTestWorkout(User $owner, \DateTimeImmutable $performedAt): Workout
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $workout = new Workout();
        $workout->owner = $owner;
        $workout->performedAt = $performedAt;

        $entityManager->persist($workout);
        $entityManager->flush();

        $this->createdWorkouts[] = $workout;

        return $workout;
    }

    private function deleteTestWorkout(Workout $workout): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $reloaded = $entityManager->getRepository(Workout::class)->find($workout->id);

        if ($reloaded instanceof Workout) {
            $entityManager->remove($reloaded);
            $entityManager->flush();
        }
    }
}
