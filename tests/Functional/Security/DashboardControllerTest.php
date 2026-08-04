<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Repository\WorkoutRepository;
use App\Repository\WorkoutStatsRepository;
use App\Service\Dashboard\DashboardPeriodCalculator;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class DashboardControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER_WITH_NO_DATA = 'user-fixture-0@test.com';

    // 26 séances (>= 2, débloque régularité + muscles semaine/mois) — pas utilisé par la suite
    // Playwright (tests/e2e/), donc pas de pollution possible entre les deux.
    private const string USER_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    private const string ADMIN = 'admin-fixture@test.com';

    /**
     * @return array<int, array<int, string>>
     */
    public static function navProvider(): array
    {
        return [
            ['Enregistre ta séance', 'app_workout'],
        ];
    }

    public function testDashboardIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER_WITH_NO_DATA, '/fr/tableau-de-bord', 'Tableau de bord | Enurus');
    }

    public function testDashboardIsNotAccessibleWhenNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged('fr/tableau-de-bord');
    }

    public function testEmptyDashboardGreetingIsFeminineForFemaleUser(): void
    {
        // user-fixture-0@test.com is female (cf. UserFixtures::loadIndexedUsers).
        $client = $this->login(self::USER_WITH_NO_DATA);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorTextContains('h2', 'prête à commencer');
    }

    public function testEmptyDashboardGreetingIsMasculineForMaleUser(): void
    {
        // user-fixture-1@test.com is male (cf. UserFixtures::loadIndexedUsers).
        $client = $this->login('user-fixture-1@test.com');
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorTextContains('h2', 'prêt à commencer');
    }

    #[DataProvider('navProvider')]
    public function testLinkNav(string $link, string $route): void
    {
        $client = $this->login(self::USER_WITH_NO_DATA);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        $client->clickLink($link);
        $this->assertRouteSame($route);
    }

    public function testRegularUserSeesContactAndMessagingLinks(): void
    {
        $client = $this->login(self::USER_WITH_NO_DATA);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorExists('a[href*="/messagerie"]');
        self::assertSelectorExists('a[href*="/contact"]');
    }

    public function testAdminDoesNotSeeContactAndMessagingLinks(): void
    {
        $client = $this->login(self::ADMIN);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorNotExists('a[href*="/messagerie"]');
        self::assertSelectorNotExists('a[href*="/contact"]');
    }

    public function testAdminSeesAdministrationLink(): void
    {
        $client = $this->login(self::ADMIN);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorExists('a[href="/admin"]');
    }

    public function testRegularUserDoesNotSeeAdministrationLink(): void
    {
        $client = $this->login(self::USER_WITH_NO_DATA);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorNotExists('a[href="/admin"]');
    }

    public function testDashboardWithWorkoutsRendersPopulatedWidgetsInsteadOfEmptyState(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextNotContains('body', 'commence ta première séance');
        // Widget Régularité réellement rendu (>= 2 séances), pas le _locked_card.html.twig —
        // "Last week" (widget de comparaison hebdomadaire) n'existe que côté widget débloqué.
        self::assertSelectorExists('[data-dashboard--session-target="exercises"]');
    }

    /**
     * `dashboard.html.twig` surchargeait `block body` en appelant `parent()` puis en redéclarant
     * `block state` dans le même bloc — Twig affiche un tag `block` à l'endroit où il est rencontré,
     * donc le contenu de `state` était rendu deux fois (une fois via `parent()`, une fois via la
     * redéclaration). Verrouille le rendu unique de chaque widget.
     */
    public function testDashboardDoesNotRenderWidgetsTwice(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('[data-controller="dashboard--session"]'));
        self::assertCount(1, $crawler->filter('[data-controller="dashboard--tonnage"]'));
        self::assertCount(1, $crawler->filter('[data-controller="dashboard--muscles"]'));
    }

    public function testDashboardSessionStatsMatchLastTrainingDay(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $user = $this->getUserByEmail(self::USER_WITH_WORKOUTS);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $lastPerformedAt = $workoutRepository->findLastPerformedAtByUser($user);
        self::assertNotNull($lastPerformedAt, 'Le fixture user doit avoir au moins une séance');

        /** @var DashboardPeriodCalculator $periodCalculator */
        $periodCalculator = static::getContainer()->get(DashboardPeriodCalculator::class);
        $day = $periodCalculator->dayOf($lastPerformedAt);

        /** @var WorkoutStatsRepository $workoutStatsRepository */
        $workoutStatsRepository = static::getContainer()->get(WorkoutStatsRepository::class);
        $dayTotals = $workoutStatsRepository->findExerciseSetRepTotals($user, $day->start, $day->end);
        $daySessionsCount = $workoutStatsRepository->countByUserAndDate($user, $day->start, $day->end);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSame(
            (string) $daySessionsCount,
            $crawler->filter('[data-dashboard--session-target="sessions"]')->text(),
            'Le nombre de séances affiché doit correspondre à la dernière journée d\'entraînement'
        );
        self::assertSame(
            (string) $dayTotals['exercises'],
            $crawler->filter('[data-dashboard--session-target="exercises"]')->text(),
            'Le nombre d\'exercices affiché doit correspondre à la dernière journée d\'entraînement'
        );
        self::assertSame(
            (string) $dayTotals['sets'],
            $crawler->filter('[data-dashboard--session-target="sets"]')->text(),
            'Le nombre de séries affiché doit correspondre à la dernière journée d\'entraînement'
        );
        self::assertSame(
            (string) $dayTotals['reps'],
            $crawler->filter('[data-dashboard--session-target="reps"]')->text(),
            'Le nombre de répétitions affiché doit correspondre à la dernière journée d\'entraînement'
        );
    }
}
