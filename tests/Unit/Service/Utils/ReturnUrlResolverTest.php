<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Utils;

use App\Service\Utils\ReturnUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class ReturnUrlResolverTest extends TestCase
{
    private const string FALLBACK_URL = 'https://enurus.com/fr/connexion';

    public function testReturnsThePreviousPageOfTheSite(): void
    {
        $request = $this->createTermsRequest('https://enurus.com/fr/inscription?invitation=abc');

        self::assertSame(
            'https://enurus.com/fr/inscription?invitation=abc',
            new ReturnUrlResolver()->resolve($request, self::FALLBACK_URL),
        );
    }

    public function testFallsBackWithoutPreviousPage(): void
    {
        $request = $this->createTermsRequest(null);

        self::assertSame(self::FALLBACK_URL, new ReturnUrlResolver()->resolve($request, self::FALLBACK_URL));
    }

    public function testFallsBackWhenThePreviousPageIsOnAnotherSite(): void
    {
        $request = $this->createTermsRequest('https://www.google.com/search?q=enurus');

        self::assertSame(self::FALLBACK_URL, new ReturnUrlResolver()->resolve($request, self::FALLBACK_URL));
    }

    public function testFallsBackWhenAnotherSiteStartsWithTheSameName(): void
    {
        $request = $this->createTermsRequest('https://enurus.com.example.org/fr/inscription');

        self::assertSame(self::FALLBACK_URL, new ReturnUrlResolver()->resolve($request, self::FALLBACK_URL));
    }

    public function testFallsBackWhenThePreviousPageIsTheCurrentOne(): void
    {
        $request = $this->createTermsRequest('https://enurus.com/fr/cgu');

        self::assertSame(self::FALLBACK_URL, new ReturnUrlResolver()->resolve($request, self::FALLBACK_URL));
    }

    private function createTermsRequest(?string $referer): Request
    {
        $request = Request::create('https://enurus.com/fr/cgu');

        if (null !== $referer) {
            $request->headers->set('Referer', $referer);
        }

        return $request;
    }
}
