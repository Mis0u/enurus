<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class DashboardControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER_WITH_NO_DATA = 'user-fixture-0@test.com';

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
}
