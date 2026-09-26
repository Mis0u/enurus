<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkoutDraftExercisesBlockControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = UserFixtures::USER_REVERSE_FLY;

    private const string URL = '/fr/enregistre-seance/bloc-exercices-brouillon';

    public function testRedirectsToLoginWhenNotLogged(): void
    {
        $client = static::createClient();

        $this->postDraft($client, '{"exercises": []}');

        $this->assertResponseRedirects('/fr/');
    }

    public function testRejectsANonXhrRequest(): void
    {
        $client = $this->login(self::USER);

        $client->request(Request::METHOD_POST, self::URL, [], [], [], '{"exercises": []}');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsAMalformedDraft(): void
    {
        $client = $this->login(self::USER);

        $this->postDraft($client, '{"exercises": "nope"}');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRendersTheDraftCardsInOrderWithTheTypedValues(): void
    {
        $client = $this->login(self::USER);
        [$first, $second] = $this->publicWeightRepsExercises();

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [
                $this->draftExercise($second, [[42.5, 8], [45.0, null]]),
                $this->draftExercise($first, [[null, 12]]),
            ],
        ]));

        $this->assertCount(2, $htmls);
        $secondCard = new Crawler($htmls[0]);
        $this->assertSame((string) $second->id, $this->inputValue($secondCard, '[exercise]'));
        $this->assertSame('42.5', $this->inputValue($secondCard, '[exerciseSets][0][weight]'));
        $this->assertSame('8', $this->inputValue($secondCard, '[exerciseSets][0][reps]'));
        $this->assertSame('45', $this->inputValue($secondCard, '[exerciseSets][1][weight]'));
        $this->assertSame('', $this->inputValue($secondCard, '[exerciseSets][1][reps]'));
        $this->assertSame((string) $first->id, $this->inputValue(new Crawler($htmls[1]), '[exercise]'));
    }

    public function testRendersCardsWithTheIndexPlaceholder(): void
    {
        $client = $this->login(self::USER);
        [$exercise] = $this->publicWeightRepsExercises();

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [$this->draftExercise($exercise, [[50.0, 10]])],
        ]));

        $this->assertStringContainsString('workout[workoutExercises][__EXERCISE_INDEX__][exercise]', $htmls[0]);
    }

    public function testNeverMentionsThePreviousSessionForTypedValues(): void
    {
        $client = $this->login(self::USER);
        [$exercise] = $this->publicWeightRepsExercises();
        $this->persistPastPerformance($exercise);

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [$this->draftExercise($exercise, [[50.0, 10]])],
        ]));

        $this->assertStringNotContainsString('js-prefilled-from', $htmls[0]);
    }

    public function testKeepsTheCarryOverNoticeOfACardStillPrefilled(): void
    {
        $client = $this->login(self::USER);
        [$exercise] = $this->publicWeightRepsExercises();
        $this->persistPastPerformance($exercise);

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [$this->draftExercise($exercise, [[65.0, 9]], prefilled: true)],
        ]));

        $card = new Crawler($htmls[0]);
        $this->assertSame('65', $this->inputValue($card, '[exerciseSets][0][weight]'));
        $this->assertStringContainsString('Repris de ta séance du 12 sept. 2026', $card->filter('.js-prefilled-from')->text());
    }

    public function testAnExerciseWithoutSetsGetsTheLastPerformance(): void
    {
        $client = $this->login(self::USER);
        [$exercise] = $this->publicWeightRepsExercises();
        $this->persistPastPerformance($exercise);

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [$this->draftExercise($exercise, [])],
        ]));

        $card = new Crawler($htmls[0]);
        $this->assertSame('62.5', $this->inputValue($card, '[exerciseSets][0][weight]'));
        $this->assertStringContainsString('js-prefilled-from', $htmls[0]);
    }

    public function testIgnoresAnotherUsersPrivateExerciseAndUnknownIds(): void
    {
        $client = $this->login(self::USER);
        $foreignExercise = $this->exerciseNamed(ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);
        [$exercise] = $this->publicWeightRepsExercises();

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [
                $this->draftExercise($foreignExercise, [[80.0, 5]]),
                [
                    'exerciseId' => '019f0000-0000-7000-8000-000000000000',
                    'sets' => [],
                ],
                $this->draftExercise($exercise, [[50.0, 10]]),
            ],
        ]));

        $this->assertCount(1, $htmls);
        $this->assertSame((string) $exercise->id, $this->inputValue(new Crawler($htmls[0]), '[exercise]'));
    }

    public function testRendersTheUsersOwnPrivateExercise(): void
    {
        $client = $this->login(self::USER);
        $ownExercise = $this->exerciseNamed(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $htmls = $this->postDraft($client, $this->toJson([
            'exercises' => [$this->draftExercise($ownExercise, [[12.0, 15]])],
        ]));

        $this->assertCount(1, $htmls);
    }

    /**
     * @return list<string>
     */
    private function postDraft(KernelBrowser $client, string $draft): array
    {
        $client->request(Request::METHOD_POST, self::URL, [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'CONTENT_TYPE' => 'application/json',
        ], $draft);

        if (! $client->getResponse()->isSuccessful()) {
            return [];
        }

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($payload);
        $this->assertIsArray($payload['htmls']);

        /** @var list<string> */
        return $payload['htmls'];
    }

    /**
     * @param list<array{?float, ?int}> $weightRepsSets
     * @return array<string, mixed>
     */
    private function draftExercise(Exercise $exercise, array $weightRepsSets, bool $prefilled = false): array
    {
        return [
            'exerciseId' => (string) $exercise->id,
            'prefilled' => $prefilled,
            'sets' => array_map(
                static fn (array $set): array => [
                    'weight' => $set[0],
                    'reps' => $set[1],
                    'duration' => null,
                    'distance' => null,
                ],
                $weightRepsSets,
            ),
        ];
    }

    private function inputValue(Crawler $card, string $nameSuffix): string
    {
        return $card->filter(\sprintf('input[name$="%s"]', $nameSuffix))->attr('value') ?? '';
    }

    /**
     * @return list<Exercise>
     */
    private function publicWeightRepsExercises(): array
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);

        /** @var list<Exercise> */
        return $repository->findBy([
            'isPublic' => true,
            'measurementType' => MeasurementType::WEIGHT_REPS,
            'bodyweightPercent' => null,
        ], [
            'name' => 'ASC',
        ], 2);
    }

    private function exerciseNamed(string $name): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $repository->findOneBy([
            'name' => $name,
        ]);
        $this->assertInstanceOf(Exercise::class, $exercise);

        return $exercise;
    }

    private function persistPastPerformance(Exercise $exercise): void
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        WorkoutTestHelper::persistPastWorkout($em, $this->getUserByEmail(self::USER), $exercise, '2026-09-12 18:00', [[62.5, 9]]);
    }
}
