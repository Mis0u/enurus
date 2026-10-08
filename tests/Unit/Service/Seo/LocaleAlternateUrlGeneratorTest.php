<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Seo;

use App\Service\Seo\LocaleAlternateUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class LocaleAlternateUrlGeneratorTest extends TestCase
{
    /**
     * @return iterable<string, array{RequestContext, string}>
     */
    public static function siteRootProvider(): iterable
    {
        yield 'https on the default port' => [new RequestContext(host: 'enurus.com', scheme: 'https'), 'https://enurus.com/'];
        yield 'http on a dev port' => [new RequestContext(host: 'localhost', httpPort: 8000), 'http://localhost:8000/'];
    }

    #[DataProvider('siteRootProvider')]
    public function testHomeDefaultVersionIsTheSiteRoot(RequestContext $context, string $expectedUrl): void
    {
        $generator = $this->createGenerator($context);

        self::assertSame($expectedUrl, $generator->defaultAlternate('app_home'));
    }

    public function testOtherPagesDefaultToTheirEnglishVersion(): void
    {
        $generator = $this->createGenerator(new RequestContext(host: 'enurus.com', scheme: 'https'));

        self::assertSame('https://enurus.com/en/terms', $generator->defaultAlternate('app_terms'));
    }

    public function testAlternatesCoverEveryLocale(): void
    {
        $generator = $this->createGenerator(new RequestContext(host: 'enurus.com', scheme: 'https'));

        $alternates = $generator->alternates('app_terms');

        self::assertCount(8, $alternates);
        self::assertSame('https://enurus.com/pl/terms', $alternates['pl']);
    }

    private function createGenerator(RequestContext $context): LocaleAlternateUrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('app_home', new Route('/{_locale}/'));
        $routes->add('app_terms', new Route('/{_locale}/terms'));

        return new LocaleAlternateUrlGenerator(new UrlGenerator($routes, $context));
    }
}
