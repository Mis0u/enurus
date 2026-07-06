<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsIndexControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string URL = '/fr/reglages';

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, self::URL, 'Réglages | FitTracker');
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }
}
