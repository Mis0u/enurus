<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class SecurityHeadersTest extends WebTestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function pageProvider(): iterable
    {
        yield 'login page' => ['/fr/'];
        yield 'sign-up page' => ['/fr/inscription'];
        yield 'not found page' => ['/fr/page-qui-nexiste-pas'];
    }

    #[DataProvider('pageProvider')]
    public function testEveryPageCarriesTheSecurityHeaders(string $url): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseHeaderSame('X-Frame-Options', 'DENY');
        self::assertResponseHeaderSame('Content-Security-Policy', "frame-ancestors 'none'");
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        self::assertResponseHeaderSame('Referrer-Policy', 'strict-origin-when-cross-origin');
        self::assertResponseHeaderSame('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    }
}
