<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\DataFixtures\UserFixtures;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class DashboardGuidedTourTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string TOUR_SELECTOR = '[data-controller~="onboarding--tour"]';

    // 26 séances — pas utilisé par la suite Playwright, cf. DashboardControllerTest.
    private const string USER_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    public function testTourIsShownOnTheVeryFirstDashboardDisplay(): void
    {
        $client = $this->login(UserFixtures::USER_GUIDED_TOUR_NOT_SEEN);
        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        $steps = json_decode($crawler->filter(self::TOUR_SELECTOR)->attr('data-onboarding--tour-steps-value') ?? '', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($steps);
        self::assertSame(
            ['welcome', 'new_workout', 'workouts', 'library', 'dashboard', 'help'],
            array_column($steps, 'target'),
        );
        $welcomeStep = $steps[0];
        self::assertIsArray($welcomeStep);
        self::assertSame('Bienvenue sur Enurus !', $welcomeStep['title'] ?? null);
    }

    /**
     * Se reconnecter sans avoir enregistré de séance ne rejoue pas le tour : il est marqué vu dès
     * son premier affichage, pas à sa fin.
     */
    public function testTourIsNotShownAgainOnTheNextDashboardDisplay(): void
    {
        $client = $this->login(UserFixtures::USER_GUIDED_TOUR_NOT_SEEN);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorNotExists(self::TOUR_SELECTOR);
    }

    public function testTourIsNotShownToAnAccountThatHasAlreadySeenIt(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorNotExists(self::TOUR_SELECTOR);
    }

    public function testTourCanBeReplayedOnDemandFromTheHelpPage(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $crawler = $client->request(Request::METHOD_GET, '/fr/aide');
        $client->click($crawler->filter('[data-guided-tour-replay]')->link());

        self::assertSelectorExists(self::TOUR_SELECTOR);
    }

    public function testFinalButtonInvitesANewcomerToLogTheirFirstWorkout(): void
    {
        $client = $this->login(UserFixtures::USER_GUIDED_TOUR_NOT_SEEN);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorExists(self::TOUR_SELECTOR . '[data-onboarding--tour-finish-label-value="Enregistrer ma première séance"]');
    }
}
