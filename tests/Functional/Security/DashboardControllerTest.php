<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Repository\WorkoutRepository;
use App\Service\Dashboard\DashboardSessionSummaryService;
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
        $this->assertPageIsAccessibleWhenLogged(self::USER_WITH_NO_DATA, '/fr/tableau-de-bord', 'Tableau de bord | FitTracker');
    }

    public function testDashboardIsNotAccessibleWhenNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged('fr/tableau-de-bord');
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

    public function testDashboardSessionStatsMatchLatestWorkout(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $user = $this->getUserByEmail(self::USER_WITH_WORKOUTS);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $lastWorkout = $workoutRepository->findLatestByUser($user);
        self::assertNotNull($lastWorkout, 'Le fixture user doit avoir au moins une séance');

        /** @var DashboardSessionSummaryService $summaryService */
        $summaryService = static::getContainer()->get(DashboardSessionSummaryService::class);
        $summary = $summaryService->summarize($lastWorkout);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertResponseIsSuccessful();
        self::assertSame(
            (string) $lastWorkout->workoutExercises->count(),
            $crawler->filter('[data-dashboard--session-target="exercises"]')->text(),
            'Le nombre d\'exercices affiché doit correspondre à la dernière séance'
        );
        self::assertSame(
            (string) $summary['totalSets'],
            $crawler->filter('[data-dashboard--session-target="sets"]')->text(),
            'Le nombre de séries affiché doit correspondre à la dernière séance'
        );
        self::assertSame(
            (string) $summary['totalReps'],
            $crawler->filter('[data-dashboard--session-target="reps"]')->text(),
            'Le nombre de répétitions affiché doit correspondre à la dernière séance'
        );
    }
}
