<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class AdminAccessTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string ADMIN = 'admin-fixture@test.com';

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/admin');

        self::assertResponseRedirects();
    }

    public function testRegularUserIsForbidden(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, '/admin');

        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanAccessDashboard(): void
    {
        $client = $this->login(self::ADMIN);
        $client->request(Request::METHOD_GET, '/admin');

        self::assertResponseRedirects();
        $client->followRedirect();

        self::assertResponseIsSuccessful();
    }
}
