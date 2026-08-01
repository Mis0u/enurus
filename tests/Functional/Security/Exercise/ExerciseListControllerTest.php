<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ExerciseListControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string URL = '/fr/bibliotheque';

    private const string USER = 'user-fixture-exercise-reverse-fly@test.com';

    // =========================================================
    // Accès
    // =========================================================

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, self::URL, 'Bibliothèque | Enurus');
    }

    // =========================================================
    // Contenu affiché
    // =========================================================

    public function testPublicExercisesAreVisible(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(
            0,
            $crawler->filter('[data-type="official"]')->count(),
            'Aucun exercice de base trouvé sur la page.',
        );
    }

    public function testUserCustomExerciseIsVisible(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();

        $customCards = $crawler->filter('[data-type="custom"]');
        $this->assertGreaterThan(
            0,
            $customCards->count(),
            'Aucun exercice custom trouvé pour cet utilisateur.',
        );
    }

    public function testOtherUserCustomExerciseIsNotVisible(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();

        $customCardNames = $crawler->filter('[data-type="custom"]')->each(
            fn ($node) => mb_strtolower($node->attr('data-name') ?? ''),
        );

        $this->assertNotContains(
            'tirage en supination',
            $customCardNames,
            "L'exercice custom d'un autre utilisateur ne doit pas apparaître.",
        );
    }

    // =========================================================
    // Ordre alphabétique
    // =========================================================

    public function testExercisesAreDisplayedInAlphabeticalOrder(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();

        /** @var string[] $names */
        $names = $crawler->filter('[data-name]')->each(
            fn ($node) => $node->attr('data-name') ?? '',
        );

        $collator = \Collator::create('fr');
        $sorted = $names;
        usort($sorted, fn (string $a, string $b) => (int) $collator->compare($a, $b));

        $this->assertSame(
            $sorted,
            $names,
            'Les exercices ne sont pas affichés dans l\'ordre alphabétique.',
        );
    }

    // =========================================================
    // Actions boutons — custom uniquement
    // =========================================================

    public function testEditAndDeleteButtonsAreVisibleOnCustomExercise(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();

        $customCard = $crawler->filter('[data-type="custom"]')->first();
        $this->assertGreaterThan(
            0,
            $customCard->count(),
            'Aucune card custom trouvée.',
        );

        $this->assertGreaterThan(
            0,
            $customCard->filter('.exercise-action-btn--edit')->count(),
            'Le bouton éditer est absent sur un exercice custom.',
        );

        $this->assertGreaterThan(
            0,
            $customCard->filter('.exercise-action-btn--delete')->count(),
            'Le bouton supprimer est absent sur un exercice custom.',
        );
    }

    public function testEditAndDeleteButtonsAreAbsentOnOfficialExercise(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();

        $officialCards = $crawler->filter('[data-type="official"]');
        $this->assertGreaterThan(0, $officialCards->count(), 'Aucune card officielle trouvée.');

        $officialCards->each(function ($card) {
            $this->assertSame(
                0,
                $card->filter('.exercise-action-btn--edit')->count(),
                'Le bouton éditer ne doit pas apparaître sur un exercice de base.',
            );
            $this->assertSame(
                0,
                $card->filter('.exercise-action-btn--delete')->count(),
                'Le bouton supprimer ne doit pas apparaître sur un exercice de base.',
            );
        });
    }
}
