<?php

declare(strict_types=1);

namespace App\Tests\Functional\ExerciseGuide;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExerciseGuidePagesTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string BENCH_PRESS_FR = '/fr/exercices/developpe-couche-barre';

    private const string USER = 'user-fixture-11-workout@test.com';

    public function testTheIndexListsGuidesUnderTheirPrimaryMuscles(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/fr/exercices');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Exercices de musculation');
        $chestSection = $crawler->filter('main section')->reduce(static fn ($section): bool => str_contains($section->filter('h2')->text(''), 'Pectoraux'));
        self::assertCount(1, $chestSection);
        self::assertCount(1, $chestSection->filter('a[href="' . self::BENCH_PRESS_FR . '"]'));
    }

    public function testAnExerciseWithoutGuideHasNoPage(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/fr/exercices/crunchs-poulie-haute');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testAGuideShowsItsTechniqueMistakesAndVariants(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::BENCH_PRESS_FR);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Développé couché (barre)');
        self::assertCount(5, $crawler->filter('#exercise-guide-steps + ol li'));
        self::assertCount(4, $crawler->filter('#exercise-guide-mistakes + ul li'));
        self::assertSelectorTextContains('main', 'Pectoraux · principal');
        self::assertSelectorTextContains('main', 'Triceps · secondaire');
    }

    /**
     * Une variante qui a sa propre fiche y mène ; les autres restent un simple nom.
     */
    public function testOnlyVariantsWithAGuideAreLinks(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::BENCH_PRESS_FR);

        $variants = $crawler->filter('#exercise-guide-variants + ul li');
        self::assertCount(3, $variants);
        self::assertCount(1, $variants->filter('a[href="/fr/exercices/developpe-incline-halteres"]'));
        self::assertSame(2, $variants->filter('div')->count());
    }

    public function testTheSilhouetteColoursTheWorkedMuscles(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::BENCH_PRESS_FR);

        $silhouette = $crawler->filter('[data-controller="workout--show--muscles"]');
        self::assertStringContainsString('pectoralis', (string) $silhouette->attr('data-workout--show--muscles-primary-value'));
        self::assertNotSame('[]', $silhouette->attr('data-workout--show--muscles-secondary-value'));
    }

    /**
     * Chaque langue a son slug, tiré du nom traduit : un slug d'une autre langue n'existe pas.
     */
    public function testEachLanguageHasItsOwnAddress(): void
    {
        $client = static::createClient();
        $client->request(Request::METHOD_GET, '/en/exercises/bench-press-barbell');
        self::assertResponseIsSuccessful();

        $client->request(Request::METHOD_GET, '/en/exercises/developpe-couche-barre');
        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testLanguageVersionsPointToTheTranslatedSlugs(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::BENCH_PRESS_FR);

        self::assertSame('http://localhost/en/exercises/bench-press-barbell', $crawler->filter('link[hreflang="en"]')->attr('href'));
        self::assertSame('http://localhost/de/uebungen/bankdruecken-langhantel', $crawler->filter('link[hreflang="de"]')->attr('href'));
        self::assertSame('http://localhost/en/exercises/bench-press-barbell', $crawler->filter('link[hreflang="x-default"]')->attr('href'));
        self::assertSame('http://localhost/de/uebungen/bankdruecken-langhantel', $crawler->filter('#language-menu a[hreflang="de"]')->attr('href'));
    }

    public function testAVisitorIsInvitedToSignUp(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, self::BENCH_PRESS_FR);

        self::assertGreaterThan(0, $crawler->filter('main a[href="/fr/inscription"]')->count());
        self::assertCount(1, $crawler->filter('header a[href="/fr/connexion"]'));
    }

    public function testALoggedInUserIsSentToTheirHistoryOfTheExercise(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::BENCH_PRESS_FR);

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('main a[href="/fr/inscription"]'));
        self::assertCount(1, $crawler->filter('main a[href^="/fr/bibliotheque/exercice/"][href$="/historique"]'));
        self::assertCount(1, $crawler->filter('header a[href="/fr/tableau-de-bord"]'));
    }

    public function testTheHomePageLinksToTheExercises(): void
    {
        $client = static::createClient();
        $crawler = $client->request(Request::METHOD_GET, '/de/');

        self::assertCount(1, $crawler->filter('header a[href="/de/uebungen"]'));
    }
}
