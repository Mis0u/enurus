<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Routine;

use App\DataFixtures\UserFixtures;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;

final class RoutineListControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string USER_WITHOUT_ROUTINE = 'user-fixture-26-workout@test.com';

    private const string ROUTE_FR = '/fr/mes-routines';

    // -------------------------------------------------------------------------
    // ACCÈS
    // -------------------------------------------------------------------------

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::ROUTE_FR);
    }

    public function testIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
    }

    // -------------------------------------------------------------------------
    // CONTENU — cas nominal
    // -------------------------------------------------------------------------

    public function testOwnerRoutinesAreDisplayed(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.routine-card__name', 'Push Day');
    }

    public function testOtherUserRoutinesAreNotDisplayed(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.routine-card__name:contains("Other User Routine")');
    }

    public function testRoutineCountIsDisplayed(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
        // Le compteur doit être présent dans le DOM
        self::assertSelectorExists('p.text-\\[12px\\]');
    }

    // -------------------------------------------------------------------------
    // CONTENU — état vide
    // -------------------------------------------------------------------------

    public function testEmptyStateIsDisplayedWhenNoRoutines(): void
    {
        // OTHER_USER n'a pas de routine dans les fixtures
        $client = $this->login(self::USER_WITHOUT_ROUTINE);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.routine-empty-state');
        self::assertSelectorNotExists('.routines-grid');
    }

    // -------------------------------------------------------------------------
    // EXERCICES — affichage
    // -------------------------------------------------------------------------

    public function testExercisesAreDisplayedInCard(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.routine-card__exercises li');
    }

    public function testMoreExercisesMessageIsDisplayedWhenOverLimit(): void
    {
        // Crée une routine avec 11 exercices pour dépasser la limite de 10
        $client = $this->login(self::OWNER);
        $this->createRoutineWithManyExercises($client, 11);

        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.routine-card__more');
    }

    public function testMoreExercisesMessageIsNotDisplayedWhenUnderLimit(): void
    {
        $client = $this->login(self::OWNER);
        $client->request(Request::METHOD_GET, self::ROUTE_FR);

        self::assertResponseIsSuccessful();

        // Push Day n'a qu'1 exercice — pas de message "et X autres"
        $cards = $client->getCrawler()->filter('.routine-card');

        $pushDayCard = null;
        foreach ($cards as $card) {
            if (str_contains($card->textContent, 'Push Day')) {
                $pushDayCard = $card;
                break;
            }
        }

        self::assertNotNull($pushDayCard);
        $moreMessage = $pushDayCard->ownerDocument?->createElement('div');
        self::assertEmpty(
            (new Crawler($pushDayCard))->filter('.routine-card__more')
        );
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    private function createRoutineWithManyExercises(
        \Symfony\Bundle\FrameworkBundle\KernelBrowser $client,
        int $count,
    ): void {
        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-routines/creer');
        $csrfToken = $crawler->filter('input[name="routine[_token]"]')->attr('value');

        /** @var ExerciseRepository $repo */
        $repo = static::getContainer()->get(ExerciseRepository::class);
        $exercises = $repo->findBy([
            'isPublic' => true,
        ], limit: $count);

        $data = array_map(
            static fn ($exercise, int $i): array => [
                'id' => (string) $exercise->id,
                'position' => $i + 1,
            ],
            $exercises,
            array_keys($exercises),
        );

        $client->request(Request::METHOD_POST, '/fr/mes-routines/creer', [
            'routine' => [
                '_token' => $csrfToken,
                'name' => 'Routine Many Exercises',
                'exercises' => json_encode($data, JSON_THROW_ON_ERROR),
            ],
        ]);
    }
}
