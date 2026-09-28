<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\DataFixtures\UserFixtures;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class DashboardComparisonTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string WIDGET = '[data-dashboard-widget="comparison"]';

    // 51 séances réparties sur 180 jours : bien plus de deux semaines d'entraînement.
    private const string USER_WITH_WEEKS_OF_HISTORY = 'user-fixture-51-workout@test.com';

    public function testTheWidgetComparesEachPeriodWithThePreviousOne(): void
    {
        $client = $this->login(self::USER_WITH_WEEKS_OF_HISTORY);
        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorExists(self::WIDGET);
        self::assertCount(3, $crawler->filter(self::WIDGET . ' [data-dashboard--goal-target="tab"]'));
        self::assertSelectorTextContains(self::WIDGET, 'Séances');
        self::assertSelectorTextContains(self::WIDGET, 'Tonnage');
        self::assertSelectorTextContains(self::WIDGET, 'Records');
        // Tonnage dans l'unité de celui qui regarde (compte en lbs).
        self::assertSelectorTextContains(self::WIDGET, 'lbs');
    }

    /**
     * Une seule séance, donc une seule semaine : la carte verrouillée dit ce qu'il manque.
     */
    public function testTheWidgetIsLockedUntilTwoDifferentWeeksOfTraining(): void
    {
        $client = $this->login(UserFixtures::USER_DASHBOARD_SINGLE);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorNotExists(self::WIDGET);
        self::assertSelectorTextContains('body', "Encore 1 semaine d'entraînement");
    }
}
