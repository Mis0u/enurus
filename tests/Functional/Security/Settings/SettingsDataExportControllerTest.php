<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Settings;

use App\Repository\WorkoutRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class SettingsDataExportControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    // 11 séances, en kg, interface en français.
    private const string USER_KG = 'user-fixture-11-workout@test.com';

    // 51 séances, en lbs.
    private const string USER_LBS = 'user-fixture-51-workout@test.com';

    private const string UTF8_BOM = "\u{FEFF}";

    public function testTheSettingsOfferBothExports(): void
    {
        $client = $this->login(self::USER_KG);
        $client->request(Request::METHOD_GET, '/fr/reglages');

        self::assertSelectorExists('a[href="/fr/reglages/export/csv"]');
        self::assertSelectorExists('a[href="/fr/reglages/export/json"]');
    }

    public function testTheCsvExportIsADownloadableSpreadsheetFile(): void
    {
        $client = $this->login(self::USER_KG);
        $csv = $this->download($client, 'csv');

        self::assertResponseHeaderSame('Content-Type', 'text/csv; charset=UTF-8');
        self::assertStringContainsString('attachment; filename=enurus-workouts-', (string) $client->getResponse()->headers->get('Content-Disposition'));
        // BOM : sans lui, Excel affiche mal les accents d'un CSV UTF-8.
        self::assertStringStartsWith(self::UTF8_BOM, $csv);
    }

    /**
     * Excel en français attend « ; » entre colonnes et la virgule décimale : sinon tout tombe dans
     * une seule colonne.
     */
    public function testTheCsvHasOneTranslatedHeaderAndOneRowPerSetInTheUserLocale(): void
    {
        $client = $this->login(self::USER_KG);
        $lines = $this->csvLines($this->download($client, 'csv'));

        // Guillemets autour des en-têtes contenant un espace : CSV standard (fputcsv), lu tel quel par Excel.
        self::assertSame('Date;Heure;Séance;"Durée de la séance (min)";Humeur;Exercice;Série;Poids;Unité;Répétitions;"Durée (s)";"Distance (m)"', $lines[0]);
        self::assertCount($this->setCountOf(self::USER_KG) + 1, $lines);
        self::assertStringContainsString(';kg;', $lines[1]);
    }

    public function testWeightsAreExportedInTheUnitOfTheUser(): void
    {
        $client = $this->login(self::USER_LBS);
        $lines = $this->csvLines($this->download($client, 'csv'));

        self::assertStringContainsString(';lbs;', $lines[1]);
    }

    public function testTheJsonExportHoldsEveryWorkoutWithRawValuesInKilograms(): void
    {
        $client = $this->login(self::USER_LBS);
        $export = json_decode($this->download($client, 'json'), true, flags: JSON_THROW_ON_ERROR);

        self::assertResponseHeaderSame('Content-Type', 'application/json');
        self::assertIsArray($export);
        self::assertSame('kg', $export['weightUnit'] ?? null);
        self::assertIsArray($export['workouts'] ?? null);
        self::assertCount($this->workoutCountOf(self::USER_LBS), $export['workouts']);

        $firstWorkout = $export['workouts'][0];
        self::assertIsArray($firstWorkout);
        self::assertIsString($firstWorkout['performedAt'] ?? null);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}$/', $firstWorkout['performedAt']);

        $firstExercise = \is_array($firstWorkout['exercises'] ?? null) ? ($firstWorkout['exercises'][0] ?? null) : null;
        self::assertIsArray($firstExercise);
        $firstSet = \is_array($firstExercise['sets'] ?? null) ? ($firstExercise['sets'][0] ?? null) : null;
        self::assertIsArray($firstSet);
        self::assertArrayHasKey('weightKg', $firstSet);
    }

    public function testAnUnknownFormatIsNotFound(): void
    {
        $client = $this->login(self::USER_KG);
        $client->request(Request::METHOD_GET, '/fr/reglages/export/xml');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testTheExportRequiresBeingLoggedIn(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged('/fr/reglages/export/csv');
    }

    private function download(KernelBrowser $client, string $format): string
    {
        $client->request(Request::METHOD_GET, '/fr/reglages/export/' . $format);
        self::assertResponseIsSuccessful();

        // Le client de test capture lui-même le contenu d'une StreamedResponse.
        return $client->getInternalResponse()->getContent();
    }

    /**
     * @return list<string>
     */
    private function csvLines(string $csv): array
    {
        return array_values(array_filter(explode("\n", str_replace(["\r\n", self::UTF8_BOM], ["\n", ''], $csv)), static fn (string $line): bool => '' !== $line));
    }

    private function workoutCountOf(string $email): int
    {
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        return $workoutRepository->countByUser($this->getUserByEmail($email));
    }

    private function setCountOf(string $email): int
    {
        $count = 0;

        foreach ($this->getUserByEmail($email)->workouts as $workout) {
            foreach ($workout->workoutExercises as $workoutExercise) {
                $count += $workoutExercise->exerciseSets->count();
            }
        }

        return $count;
    }
}
