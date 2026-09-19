<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SettingsIndexControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    // >= 2 séances : débloque aussi le widget Régularité, contrairement à USER (0 séance).
    private const string USER_WITH_WORKOUTS = 'user-fixture-26-workout@test.com';

    private const string URL = '/fr/reglages';

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, self::URL, 'Réglages | Enurus');
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }

    public function testNoWidgetToggleIsProposedForAUserWithoutAnyWorkout(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::URL);

        self::assertSelectorNotExists('[data-controller="settings--dashboard-widgets"]');
    }

    public function testUnlockedWidgetsAreProposedAsCheckboxesForAUserWithWorkouts(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        self::assertResponseIsSuccessful();
        // Session, Tonnage, Muscles, Régularité — pas Objectifs, ce fixture n'en a jamais créé.
        self::assertCount(4, $crawler->filter('[data-controller="settings--dashboard-widgets"] input[type="checkbox"]'));
    }

    public function testAWidgetHiddenByTheUserIsRenderedUnchecked(): void
    {
        $client = $this->login(self::USER_WITH_WORKOUTS);
        $user = $this->getUserByEmail(self::USER_WITH_WORKOUTS);
        $user->hiddenWidgets = ['tonnage'];
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->flush();

        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $checkbox = $crawler->filter('input[data-settings--dashboard-widgets-widget-param="tonnage"]');
        self::assertFalse('checked' === $checkbox->attr('checked'));
    }
}
