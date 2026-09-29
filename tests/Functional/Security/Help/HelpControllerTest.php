<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Help;

use App\Enum\Help\HelpSectionEnum;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use App\Tests\Functional\Security\Trait\SwitchesFeatureSettingTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class HelpControllerTest extends WebTestCase
{
    use FunctionalTestTrait;
    use SwitchesFeatureSettingTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string HELP_URL = '/fr/aide';

    public function testAnonymousVisitorIsRedirectedToLogin(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_GET, self::HELP_URL);

        self::assertResponseRedirects();
    }

    public function testEverySectionHasItsAnchorAndAnEntryInTheSummary(): void
    {
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, self::HELP_URL);

        self::assertResponseIsSuccessful();
        foreach (HelpSectionEnum::cases() as $section) {
            self::assertCount(1, $crawler->filter(\sprintf('details#%s', $section->value)), $section->value);
            self::assertCount(1, $crawler->filter(\sprintf('nav a[href="#%s"]', $section->value)), $section->value);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function helpUrlPerLocale(): iterable
    {
        yield 'fr' => ['/fr/aide'];
        yield 'en' => ['/en/help'];
        yield 'it' => ['/it/aiuto'];
        yield 'es' => ['/es/ayuda'];
        yield 'pt' => ['/pt/ajuda'];
        yield 'de' => ['/de/hilfe'];
        yield 'nl' => ['/nl/hulp'];
        yield 'pl' => ['/pl/pomoc'];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function helpUrlPerLocaleAndPhotoSwitch(): iterable
    {
        foreach (self::helpUrlPerLocale() as $locale => [$url]) {
            yield $locale . ' photo on' => [$url, true];
            yield $locale . ' photo off' => [$url, false];
        }
    }

    #[DataProvider('helpUrlPerLocaleAndPhotoSwitch')]
    public function testEveryLanguageShowsTranslatedTextInsteadOfRawKeys(string $url, bool $photoUploadEnabled): void
    {
        $client = $this->login(self::USER);
        $this->switchWorkoutPhotoUpload($photoUploadEnabled);

        $crawler = $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('section.', $crawler->filter('main')->text());
    }

    public function testSectionsLinkToThePageTheyDescribe(): void
    {
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, self::HELP_URL);

        self::assertCount(1, $crawler->filter('details#library a[href="/fr/bibliotheque"]'));
    }

    public function testHelpIsReachableFromTheNavigation(): void
    {
        $client = $this->login(self::USER);

        $crawler = $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertGreaterThanOrEqual(1, $crawler->filter('a[href="' . self::HELP_URL . '"]')->count());
    }

    public function testWorkoutLogSectionMentionsThePhotoOnlyWhenUploadIsEnabled(): void
    {
        $client = $this->login(self::USER);
        $this->switchWorkoutPhotoUpload(true);
        $withPhoto = $client->request(Request::METHOD_GET, self::HELP_URL)->filter('details#workout_log')->text();

        $this->switchWorkoutPhotoUpload(false);
        $withoutPhoto = $client->request(Request::METHOD_GET, self::HELP_URL)->filter('details#workout_log')->text();

        self::assertStringContainsString('photo', $withPhoto);
        self::assertStringNotContainsString('photo', $withoutPhoto);
    }
}
