<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Contact;

use App\DataFixtures\UserFixtures;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ContactShowControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-1@test.com';

    private const string URL = '/fr/contact';

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, self::URL, 'Contact | FitTracker');
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }

    public function testFormIsDisplayedWhenNotRestricted(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::URL);

        self::assertSelectorExists('form[name="contact_form"]');
    }

    public function testFormIsHiddenWhenRestrictedTemporarily(): void
    {
        $client = $this->login(UserFixtures::USER_RESTRICTED_ONE_WEEK);
        $client->request(Request::METHOD_GET, self::URL);

        self::assertSelectorNotExists('form[name="contact_form"]');
        $this->assertSelectorTextContains('body', '1 semaine');
        $this->assertSelectorTextContains('body', 'Restriction levée le');
    }

    public function testFormIsHiddenWhenRestrictedPermanently(): void
    {
        $client = $this->login(UserFixtures::USER_RESTRICTED_PERMANENT);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        self::assertSelectorNotExists('form[name="contact_form"]');
        $this->assertSelectorTextContains('body', 'suspension permanente');
        self::assertStringNotContainsString('Restriction levée le', $crawler->filter('body')->text());
    }
}
