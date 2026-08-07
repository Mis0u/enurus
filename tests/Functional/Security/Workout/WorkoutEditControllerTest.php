<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\ExerciseSet;
use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkoutEditControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-26-workout@test.com';

    private const string OTHER_USER = 'user-fixture-11-workout@test.com';

    // -------------------------------------------------------------------------
    // Accès / Sécurité
    // -------------------------------------------------------------------------

    public function testIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $this->assertPageIsAccessibleWhenLogged(
            self::USER,
            $this->getEditUrl($workout),
            'Modifier la séance | Enurus',
            $client,
        );
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $url = $this->getEditUrlFromClient(self::USER);
        $this->assertPageIsRedirectToLoginWhenNotLogged($url, $client);
    }

    public function testCannotEditWorkoutOfAnotherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $workoutOfUser = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_GET, $this->getEditUrl($workoutOfUser));
        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // Soumission valide
    // -------------------------------------------------------------------------

    public function testValidEditIsPersisted(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['duration'] = 99;

        $client->request(Request::METHOD_POST, $url, $payload);
        $this->assertResponseRedirects();

        $updated = $this->findUpdatedWorkout($workout->id);
        $this->assertSame(99, $updated->duration);
    }

    public function testEditRedirectsToShowAfterSuccess(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);

        $client->request(Request::METHOD_POST, $url, $payload);
        $this->assertResponseRedirects(\sprintf('/fr/seance/%s', $workout->id));
    }

    public function testEditUpdatesNote(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['note'] = 'Note modifiée par le test.';

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        $this->assertSame('Note modifiée par le test.', $updated->note);
    }

    public function testEditUpdatesSetWeight(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['workoutExercises'][0]['exerciseSets'][0]['weight'] = 999.0;

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        $firstSet = $this->getFirstSet($updated);
        $this->assertSame(999.0, $firstSet->weight);
    }

    public function testEditUpdatesSetReps(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['workoutExercises'][0]['exerciseSets'][0]['reps'] = 42;

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        $firstSet = $this->getFirstSet($updated);
        $this->assertSame(42, $firstSet->reps);
    }

    public function testEditUpdatesPerformedAt(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $originalTime = $workout->performedAt->format('H:i:s');
        $payload['workout']['performedAt'] = '2025-01-15';

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        // La date change, mais l'heure d'origine est préservée (pas de sélecteur d'heure dans le
        // formulaire) — éditer une séance ne doit jamais changer son tri parmi celles du même jour.
        $this->assertSame('2025-01-15 ' . $originalTime, $updated->performedAt->format('Y-m-d H:i:s'));
    }

    /**
     * Régression : le champ `routine` du formulaire n'est jamais rendu en édition (aucune UI pour
     * changer la routine d'une séance déjà enregistrée) — un mapped field absent des données
     * soumises est traité par Symfony comme soumis vide, ce qui écrasait silencieusement
     * Workout::$routine à null à chaque édition, quel que soit le champ réellement modifié.
     */
    public function testEditPreservesRoutine(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $routine = $this->attachRoutineToWorkout($em, $workout);

        $url = $this->getEditUrl($workout);
        $crawler = $client->request(Request::METHOD_GET, $url);
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');
        $this->assertNotNull($csrfToken);

        $payload = $this->buildValidPayload($workout, $csrfToken);
        $payload['workout']['duration'] = 99;

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        $this->assertNotNull($updated->routine);
        $this->assertSame((string) $routine->id, (string) $updated->routine->id);
    }

    public function testEditFormRendersDateDuplicateCheckExcludingCurrentWorkout(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $crawler = $client->request(Request::METHOD_GET, $this->getEditUrl($workout));
        $dateInput = $crawler->filter('#workout_performedAt');

        self::assertStringContainsString('date', (string) $dateInput->attr('data-controller'));
        self::assertSame((string) $workout->id, $dateInput->attr('data-date-exclude-id-value'));
    }

    // -------------------------------------------------------------------------
    // Suppression d'un set
    // -------------------------------------------------------------------------

    public function testDeleteOneSetIsPersisted(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);

        $originalSetCount = \count($payload['workout']['workoutExercises'][0]['exerciseSets']);
        array_pop($payload['workout']['workoutExercises'][0]['exerciseSets']);

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $updated->workoutExercises->first();
        $this->assertSame($originalSetCount - 1, $firstExercise->exerciseSets->count());
    }

    // -------------------------------------------------------------------------
    // Suppression d'un exercice entier
    // -------------------------------------------------------------------------

    public function testDeleteOneExerciseIsPersisted(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);

        $originalExerciseCount = \count($payload['workout']['workoutExercises']);
        array_pop($payload['workout']['workoutExercises']);

        $client->request(Request::METHOD_POST, $url, $payload);

        $updated = $this->findUpdatedWorkout($workout->id);
        $this->assertSame($originalExerciseCount - 1, $updated->workoutExercises->count());
    }

    // -------------------------------------------------------------------------
    // Soumission invalide (bypass)
    // -------------------------------------------------------------------------

    public function testInvalidEditShowsFlashError(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getEditUrl($workout), [
            'workout' => [
                '_token' => 'invalid-token',
            ],
        ]);

        $this->assertResponseRedirects();
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testEmptyWeightIsRejected(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['workoutExercises'][0]['exerciseSets'][0]['weight'] = '';

        $client->request(Request::METHOD_POST, $url, $payload);
        $this->assertResponseRedirects();
    }

    public function testEmptyRepsIsRejected(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['workoutExercises'][0]['exerciseSets'][0]['reps'] = '';

        $client->request(Request::METHOD_POST, $url, $payload);
        $this->assertResponseRedirects();
    }

    public function testNegativeRepsIsRejected(): void
    {
        [$client, $workout, $url, $payload] = $this->prepareEditRequest(self::USER);
        $payload['workout']['workoutExercises'][0]['exerciseSets'][0]['reps'] = 0;

        $client->request(Request::METHOD_POST, $url, $payload);
        $this->assertResponseRedirects();
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    /**
     * Prépare un client logué, le workout, l'URL et le payload valide en une seule étape.
     * Evite la répétition login → getFirstWorkout → getEditUrl → GET → csrfToken → buildValidPayload.
     *
     * @return array{0: KernelBrowser, 1: Workout, 2: string, 3: array{workout: array{_token: string, performedAt: non-empty-string, duration: int|null, workoutExercises: array<int, array{exercise: string, position: int, exerciseSets: array<int, array{weight: float, reps: int, position: int}>}>}}}
     */
    private function prepareEditRequest(string $email): array
    {
        $client = $this->login($email);
        $workout = $this->getFirstWorkout($email);
        $url = $this->getEditUrl($workout);

        $crawler = $client->request(Request::METHOD_GET, $url);
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');
        $this->assertNotNull($csrfToken);

        return [$client, $workout, $url, $this->buildValidPayload($workout, $csrfToken)];
    }

    private function attachRoutineToWorkout(EntityManagerInterface $em, Workout $workout): Routine
    {
        /** @var User $owner */
        $owner = $workout->owner;

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Fessier';

        $workout->routine = $routine;

        $em->persist($routine);
        $em->flush();

        return $routine;
    }

    private function getFirstWorkout(string $email): Workout
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        return WorkoutTestHelper::getFirstWorkout($userRepository, $workoutRepository, $email);
    }

    private function getEditUrl(Workout $workout): string
    {
        return \sprintf('/fr/seance/%s/modifier', $workout->id);
    }

    private function getEditUrlFromClient(string $email): string
    {
        return $this->getEditUrl($this->getFirstWorkout($email));
    }

    private function findUpdatedWorkout(mixed $id): Workout
    {
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $updated = $workoutRepository->find($id);
        $this->assertNotNull($updated);

        /** @var Workout $updated */
        return $updated;
    }

    private function getFirstSet(Workout $workout): ExerciseSet
    {
        /** @var WorkoutExercise $firstExercise */
        $firstExercise = $workout->workoutExercises->first();
        /** @var ExerciseSet $firstSet */
        $firstSet = $firstExercise->exerciseSets->first();

        return $firstSet;
    }

    /**
     * @return array{workout: array{_token: string, performedAt: non-empty-string, duration: int|null, workoutExercises: array<int, array{exercise: string, position: int, exerciseSets: array<int, array{weight: float, reps: int, position: int}>}>}}
     */
    private function buildValidPayload(Workout $workout, string $csrfToken): array
    {
        $workoutExercises = [];

        foreach ($workout->workoutExercises->toArray() as $index => $workoutExercise) {
            $sets = [];
            foreach ($workoutExercise->exerciseSets->toArray() as $setIndex => $set) {
                $sets[$setIndex] = [
                    'weight' => $set->weight,
                    'reps' => $set->reps,
                    'position' => $setIndex,
                ];
            }
            $workoutExercises[$index] = [
                'exercise' => (string) $workoutExercise->exercise->id,
                'position' => $index,
                'exerciseSets' => $sets,
            ];
        }

        return [
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => $workout->performedAt->format('Y-m-d\TH:i'),
                'duration' => $workout->duration,
                'workoutExercises' => $workoutExercises,
            ],
        ];
    }
}
