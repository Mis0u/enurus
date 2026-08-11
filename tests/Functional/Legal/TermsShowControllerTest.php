<?php

declare(strict_types=1);

namespace App\Tests\Functional\Legal;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TermsShowControllerTest extends WebTestCase
{
    public function testIsAccessibleWithoutLogin(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/cgu');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', "Conditions Générales d'Utilisation");
    }

    public function testOnlyFrenchNoticeIsHiddenOnFrenchLocale(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/cgu');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertSelectorNotExists('.text-amber-300');
    }

    public function testOnlyFrenchNoticeIsShownOnOtherLocale(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/en/terms');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.text-amber-300');
    }
}
