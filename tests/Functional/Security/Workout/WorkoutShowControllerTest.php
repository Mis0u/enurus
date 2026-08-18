<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Repository\UserRepository;
use App\Repository\WorkoutExerciseRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class WorkoutShowControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    // Utilisateur avec note sur son 1er workout
    private const string USER_WITH_NOTE = 'user-fixture-26-workout@test.com';

    // Utilisateur avec unité de mesure en lbs
    private const string USER_LBS = 'user-fixture-51-workout@test.com';

    // Utilisateur sans séance — pour tester l'isolation des données
    private const string USER_EMPTY = 'user-fixture-0@test.com';

    public function testIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::USER_WITH_NOTE);
        $url = $this->getWorkoutUrl(self::USER_WITH_NOTE);
        $this->assertPageIsAccessibleWhenLogged(self::USER_WITH_NOTE, $url, 'Ma séance | Enurus', $client);
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $url = $this->getWorkoutUrl(self::USER_WITH_NOTE);
        $this->assertPageIsRedirectToLoginWhenNotLogged($url, $client);
    }

    public function testReturns403WhenUserAccessesOtherUserWorkout(): void
    {
        $client = $this->login(self::USER_EMPTY);
        $url = $this->getWorkoutUrl(self::USER_WITH_NOTE);
        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testReturns404WithUnknownUuid(): void
    {
        $client = $this->login(self::USER_WITH_NOTE);
        $client->request(Request::METHOD_GET, '/fr/seance/00000000-0000-0000-0000-000000000000');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testWorkoutTitleIsDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            'h2',
            'Le titre de la séance (h2) doit être présent'
        );
    }

    public function testMetricTonnageIsDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            '.show-metric-tonnage',
            'Le bloc tonnage doit être affiché'
        );
    }

    public function testMetricDurationIsDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            '.show-metric-duration',
            'Le bloc durée doit être affiché'
        );
    }

    public function testExerciseCardsAreDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            '.show-exercise-card',
            'Au moins une card exercice doit être affichée'
        );
    }

    public function testNoteIsDisplayedWhenPresent(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            '.show-note',
            'Le bloc note doit être affiché quand une note est présente'
        );
    }

    public function testNoteIsNotDisplayedWhenAbsent(): void
    {
        $this->displayElement(
            self::USER_LBS,
            '.show-note',
            'Le bloc note ne doit pas être affiché si aucune note',
            false
        );
    }

    public function testMuscleTagsAreDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            '.show-muscle-tag-primary',
            'Au moins un tag muscle primaire doit être affiché'
        );
    }

    public function testPrimaryMuscleTagsAreDisplayedBeforeSecondary(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_WITH_NOTE);

        $this->assertResponseIsSuccessful();

        $firstCard = $crawler->filter('.show-exercise-card')->first();
        $firstTag = $firstCard->filter('.show-muscle-tag-primary, .show-muscle-tag-secondary')->first();

        $this->assertStringContainsString(
            'show-muscle-tag-primary',
            (string) $firstTag->attr('class'),
            'Le premier tag muscle doit être primaire'
        );
    }

    public function testSetsTableIsDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            'table tbody tr',
            'Au moins une ligne de série doit être affichée'
        );
    }

    public function testBackLinkIsPresent(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            'a.show-back-btn',
            'Le lien retour doit être présent'
        );
    }

    public function testBackLinkPointsToWorkoutList(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_WITH_NOTE);

        $this->assertResponseIsSuccessful();
        $backLink = $crawler->filter('a.show-back-btn')->first()->attr('href');
        $this->assertSame('/fr/mes-seances', $backLink, 'Le lien retour doit pointer vers /fr/mes-seances');
    }

    public function testWeightIsDisplayedInLbsForLbsUser(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_LBS);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            UnitOfMeasureEnum::LBS->value,
            $crawler->filter('.show-chip-tonnage')->text(),
            'Le tonnage global doit être affiché en lbs pour un user lbs'
        );
    }

    public function testWeightIsDisplayedInKgForKgUser(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_WITH_NOTE);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            UnitOfMeasureEnum::KG->value,
            $crawler->filter('.show-chip-tonnage')->text(),
            'Le tonnage global doit être affiché en kg pour un user kg'
        );
    }

    public function testMusclesSilhouetteContainerIsPresent(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_WITH_NOTE);

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            1,
            $crawler->filter('.svg-body-container'),
            'Le conteneur de la silhouette musculaire doit être présent'
        );
    }

    public function testExerciseTonnageIsDisplayed(): void
    {
        $this->displayElement(
            self::USER_WITH_NOTE,
            '.show-exercise-tonnage-value',
            'Le tonnage par exercice doit être affiché'
        );
    }

    public function testTimeBasedExerciseCardShowsTotalDurationAlongsideTonnage(): void
    {
        $client = static::createClient();

        $found = $this->findWorkoutForMeasurementType(MeasurementType::TIME);

        if (null === $found) {
            self::markTestSkipped('Aucune séance avec un exercice TIME dans les fixtures chargées.');
        }

        [$owner, $url, $exerciseName] = $found;

        $client->loginUser($owner);
        $crawler = $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();

        $card = $crawler->filter('.show-exercise-card')
            ->reduce(static fn (Crawler $node): bool => str_contains($node->text(), $exerciseName))
            ->first();

        $labels = $card->filter('.show-exercise-tonnage-label')->each(static fn (Crawler $node): string => trim($node->text()));
        $values = $card->filter('.show-exercise-tonnage-value')->each(static fn (Crawler $node): string => trim($node->text()));

        $this->assertSame(['Tonnage', 'Durée'], $labels, 'Le tonnage et la durée totale doivent être affichés côte à côte');
        $this->assertMatchesRegularExpression('/^\d+:\d{2}$/', $values[1] ?? '', 'La durée totale doit être au format mm:ss');
    }

    public function testFemaleSilhouetteIsDisplayedForFemaleUser(): void
    {
        $this->identifySilhouette(self::USER_WITH_NOTE, GenderEnum::FEMALE->value);
    }

    public function testMaleSilhouetteIsDisplayedForMaleUser(): void
    {
        $this->identifySilhouette(self::USER_LBS, GenderEnum::MALE->value);
    }

    public function testPrimaryMuscleColorIsAppliedViaStimulusData(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_WITH_NOTE);

        $this->assertResponseIsSuccessful();

        $primaryValue = $crawler->filter('[data-workout--show--muscles-primary-value]')->first()->attr('data-workout--show--muscles-primary-value');

        $this->assertNotEmpty(
            $primaryValue,
            'Les IDs SVG primaires doivent être présents dans le data attribute Stimulus'
        );

        $ids = json_decode((string) $primaryValue, true);
        $this->assertIsArray($ids, 'Les IDs SVG primaires doivent être un tableau JSON valide');
        $this->assertNotEmpty($ids, 'Le tableau des IDs SVG primaires ne doit pas être vide');
    }

    public function testSecondaryMuscleColorDataIsPresent(): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn(self::USER_WITH_NOTE);

        $this->assertResponseIsSuccessful();

        $secondaryValue = $crawler->filter('[data-workout--show--muscles-secondary-value]')->first()->attr('data-workout--show--muscles-secondary-value');
        $ids = json_decode((string) $secondaryValue, true);

        $this->assertIsArray($ids, 'Les IDs SVG secondaires doivent être un tableau JSON valide');
    }

    /**
     * @return ?array{0: User, 1: string, 2: string} owner, URL séance, nom traduit de l'exercice
     */
    private function findWorkoutForMeasurementType(MeasurementType $measurementType): ?array
    {
        /** @var WorkoutExerciseRepository $workoutExerciseRepository */
        $workoutExerciseRepository = static::getContainer()->get(WorkoutExerciseRepository::class);

        $workoutExercise = $workoutExerciseRepository->createQueryBuilder('we')
            ->join('we.exercise', 'e')
            ->join('we.workout', 'w')
            ->addSelect('e', 'w')
            ->andWhere('e.measurementType = :measurementType')
            ->setParameter('measurementType', $measurementType)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (! $workoutExercise instanceof WorkoutExercise) {
            return null;
        }

        /** @var TranslatorInterface $translator */
        $translator = self::getContainer()->get(TranslatorInterface::class);

        $exerciseName = $workoutExercise->exercise->isPublic
            ? $translator->trans($workoutExercise->exercise->name, [], 'exercise', 'fr')
            : $workoutExercise->exercise->name;

        return [$workoutExercise->workout->owner, \sprintf('/fr/seance/%s', $workoutExercise->workout->id), $exerciseName];
    }

    private function getWorkoutUrl(string $userEmail): string
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => $userEmail,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        /** @var Workout $workout */
        $workout = $workoutRepository->findOneBy([
            'owner' => $user,
        ]);

        return \sprintf('/fr/seance/%s', $workout->id);
    }

    private function displayElement(string $email, string $selector, string $message, bool $visible = true): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn($email);

        $this->assertResponseIsSuccessful();
        if ($visible) {
            $this->assertGreaterThan(
                0,
                $crawler->filter($selector)->count(),
                $message
            );
        } else {
            $this->assertCount(
                0,
                $crawler->filter($selector),
                $message
            );
        }
    }

    private function identifySilhouette(string $email, string $gender): void
    {
        $crawler = $this->requestToWorkoutWhenLoggedIn($email);

        $sexe = ['masculine', 'masculin'];

        if (GenderEnum::FEMALE->value === $gender) {
            $sexe = ['féminine', 'féminin'];
        }

        // Scopé à .svg-body-container (silhouette visible) : la carte de partage hors-écran
        // (_share_card.html.twig) réutilise le même partial SVG_BODY et porterait sinon les
        // mêmes ids, ce qui fausserait le compte attendu de 1.
        $this->assertResponseIsSuccessful();
        $this->assertCount(
            1,
            $crawler->filter(\sprintf('.svg-body-container [id="front-%s"]', $gender)),
            \sprintf('La silhouette %s face doit être affichée pour un user %s', $sexe[0], $sexe[1])
        );
        $this->assertCount(
            1,
            $crawler->filter(\sprintf('.svg-body-container [id="back-%s"]', $gender)),
            \sprintf('La silhouette %s dos doit être affichée pour un user %s', $sexe[0], $sexe[1])
        );
    }

    private function requestToWorkoutWhenLoggedIn(string $email): Crawler
    {
        $client = $this->login($email);
        $url = $this->getWorkoutUrl($email);
        return $client->request(Request::METHOD_GET, $url);
    }
}
