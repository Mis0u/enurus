<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig\Components\LiveComponent;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\RoutineFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Twig\Components\LiveComponent\ExerciseSelectorComponent;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class ExerciseSelectorComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string COMPONENT_NAME = 'LiveComponent:ExerciseSelectorComponent:ExerciseSelectorComponent';

    /**
     * Exercice public (« Ab wheel (rollout) » en français) — alphabétiquement avant l'exercice
     * privé `Reverse fly` de `USER_REVERSE_FLY`.
     */
    private const string PUBLIC_AB_WHEEL = 'ab_wheel_rollout.name';

    public function testExercisesAreEmptyWhenClosed(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $component = $this->component($testComponent);

        self::assertFalse($component->isOpen);
        self::assertSame([], $component->getFilteredExercises());
    }

    public function testOpenActionMakesExercisesAvailable(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [], $client);
        $testComponent->call('open');

        // component() ré-hydrate localement les props (pas de requête HTTP) : le contexte de
        // sécurité request-scoped nécessaire à getUser() n'est plus disponible une fois la vraie
        // requête de call() terminée — render() se contente de relire le HTML déjà renvoyé par
        // cette requête (qui, elle, s'est bien exécutée authentifiée), donc fiable ici.
        $crawler = $testComponent->render()->crawler();

        self::assertGreaterThan(0, $crawler->filter('input[type="checkbox"][data-model="norender|selectedIds[]"]')->count());
    }

    public function testCloseActionResetsSearchAndFilters(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'search' => 'reverse',
            'isOpen' => true,
            'muscleGroupFilters' => [
                'some-id' => 'primary',
            ],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('close');

        $component = $this->component($testComponent);
        self::assertFalse($component->isOpen);
        self::assertSame('', $component->search);
        self::assertSame([], $component->muscleGroupFilters);
    }

    /**
     * 1er clic = primaire, 2e clic = secondaire, 3e clic = retiré du filtre — même comportement
     * que le filtre muscle des routines.
     */
    public function testCycleMuscleFilterCyclesThroughPrimarySecondaryThenNone(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $muscleGroupId = $this->component($testComponent)->getMuscleGroups()[0]->id;

        $testComponent->call('cycleMuscleFilter', [
            'id' => (string) $muscleGroupId,
        ]);
        self::assertSame('primary', $this->component($testComponent)->muscleGroupFilters[(string) $muscleGroupId]);

        $testComponent->call('cycleMuscleFilter', [
            'id' => (string) $muscleGroupId,
        ]);
        self::assertSame('secondary', $this->component($testComponent)->muscleGroupFilters[(string) $muscleGroupId]);

        $testComponent->call('cycleMuscleFilter', [
            'id' => (string) $muscleGroupId,
        ]);
        self::assertArrayNotHasKey((string) $muscleGroupId, $this->component($testComponent)->muscleGroupFilters);
    }

    public function testMuscleFilterOnlyMatchesRequestedType(): void
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $exerciseRepository->findOneBy([
            'name' => ExerciseFixtures::EXERCISE_REVERSE_FLY,
        ]);
        self::assertNotNull($exercise);

        $primaryMuscleGroupId = null;
        $secondaryMuscleGroupId = null;
        foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
            if ('primary' === $exerciseMuscle->type->value) {
                $primaryMuscleGroupId = (string) $exerciseMuscle->muscleGroup->id;
            } else {
                $secondaryMuscleGroupId = (string) $exerciseMuscle->muscleGroup->id;
            }
        }
        self::assertNotNull($primaryMuscleGroupId, 'Fixture exercise must have a primary muscle group.');

        // Filtré sur son groupe primaire réel : l'exercice apparaît
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'muscleGroupFilters' => [
                $primaryMuscleGroupId => 'primary',
            ],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $names = array_map(
            static fn ($e): string => $e->name,
            $this->component($testComponent)->getFilteredExercises()
        );
        self::assertContains(ExerciseFixtures::EXERCISE_REVERSE_FLY, $names);

        if (null !== $secondaryMuscleGroupId) {
            // Même groupe, mais demandé en secondaire alors qu'il est réellement primaire : ne matche pas
            $testComponentWrongType = $this->createLiveComponent(self::COMPONENT_NAME, [
                'isOpen' => true,
                'muscleGroupFilters' => [
                    $primaryMuscleGroupId => 'secondary',
                ],
            ]);
            $testComponentWrongType->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

            $namesWrongType = array_map(
                static fn ($e): string => $e->name,
                $this->component($testComponentWrongType)->getFilteredExercises()
            );
            self::assertNotContains(ExerciseFixtures::EXERCISE_REVERSE_FLY, $namesWrongType);
        }
    }

    public function testAddSelectedExercisesDispatchesOneCardPerExerciseInSelectionOrder(): void
    {
        $reverseFlyId = $this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $abWheelId = $this->getExerciseIdByName(self::PUBLIC_AB_WHEEL);
        // Ordre de coche volontairement différent de l'ordre alphabétique.
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [$reverseFlyId, $abWheelId],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('addSelectedExercises');

        $htmls = $this->dispatchedHtmls($testComponent);
        self::assertCount(2, $htmls);
        self::assertStringContainsString('[exercise]" value="' . $reverseFlyId . '"', $htmls[0]);
        self::assertStringContainsString('[exercise]" value="' . $abWheelId . '"', $htmls[1]);

        $component = $this->component($testComponent);
        self::assertFalse($component->isOpen);
        self::assertSame([], $component->selectedIds);
    }

    /**
     * Le HTML est rendu côté serveur et envoyé directement dans l'événement (ancien
     * `workout_exercise_block`, supprimé — 2 aller-retours HTTP par exercice ajouté, réduit à 1).
     * `index` reste un placeholder littéral : seul le controller Stimulus connaît la position
     * réelle dans la liste déjà affichée au moment de l'insertion.
     */
    public function testAddedCardIsServerRenderedWithAnIndexPlaceholder(): void
    {
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [(string) $exercise->id],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('addSelectedExercises');

        $html = $this->dispatchedHtmls($testComponent)[0];
        self::assertStringContainsString('data-exercise-index="__EXERCISE_INDEX__"', $html);
        self::assertStringContainsString('name="workout[workoutExercises][__EXERCISE_INDEX__][exerciseSets][0][weight]"', $html);
        self::assertStringContainsString('name="workout[workoutExercises][__EXERCISE_INDEX__][position]"', $html);

        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        self::assertStringContainsString($translator->trans($exercise->name, [], 'exercise', 'fr'), $html);
        self::assertNotEmpty($exercise->exerciseMuscles);
        self::assertStringContainsString('text-[#f43f5e]', $html);
    }

    /**
     * Le composant est partagé entre la création (`exercise`) et l'édition
     * (`workout--edit--exercise`) d'une séance — la carte rendue doit brancher ses `data-action`
     * sur le bon controller Stimulus selon le contexte.
     */
    public function testAddedCardUsesControllerNameFromEditContext(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'controllerName' => 'workout--edit--exercise',
            'selectedIds' => [$this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY)],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('addSelectedExercises');

        $html = $this->dispatchedHtmls($testComponent)[0];
        self::assertStringContainsString('click->workout--edit--exercise#deleteExercise', $html);
        self::assertStringNotContainsString('click->exercise#deleteExercise', $html);
    }

    public function testAddedCardIsPrefilledWithTheLastPerformance(): void
    {
        $user = $this->getUserByEmail(UserFixtures::USER_REVERSE_FLY);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        WorkoutTestHelper::persistPastWorkout($em, $user, $exercise, '2026-09-12 18:00', [[12.5, 15], [15.0, 12]]);

        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [(string) $exercise->id],
        ]);
        $testComponent->actingAs($user);
        $testComponent->call('addSelectedExercises');

        $html = $this->dispatchedHtmls($testComponent)[0];
        self::assertMatchesRegularExpression('/\[exerciseSets\]\[0\]\[weight\]"\s+value="12.5"/', $html);
        self::assertMatchesRegularExpression('/\[exerciseSets\]\[1\]\[reps\]"\s+value="12"/', $html);
        // Le composant de test est appelé sans préfixe `/{_locale}` : rendu dans la locale par
        // défaut (en). En vrai, la route des LiveComponents est préfixée par la locale.
        self::assertStringContainsString('Carried over from your workout on Sep 12, 2026', $html);
    }

    public function testAddedCardOfANeverPerformedExerciseIsEmpty(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [$this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY)],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('addSelectedExercises');

        $html = $this->dispatchedHtmls($testComponent)[0];
        self::assertStringNotContainsString('[exerciseSets][1]', $html);
        self::assertStringNotContainsString('Carried over from your workout', $html);
    }

    /**
     * Les identifiants viennent du navigateur : seuls les exercices réellement proposés à
     * l'utilisateur (publics ou à lui, non archivés) peuvent être ajoutés — jamais l'exercice
     * privé d'un autre utilisateur, ni un identifiant inconnu.
     */
    public function testAddSelectedExercisesIgnoresExercisesNotAvailableToTheUser(): void
    {
        $reverseFlyId = $this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [
                $this->getExerciseIdByName(RoutineFixtures::EXERCISE_OTHER_USER),
                '00000000-0000-0000-0000-000000000000',
                $reverseFlyId,
            ],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('addSelectedExercises');

        $htmls = $this->dispatchedHtmls($testComponent);
        self::assertCount(1, $htmls);
        self::assertStringContainsString('[exercise]" value="' . $reverseFlyId . '"', $htmls[0]);
    }

    public function testAddSelectedExercisesWithNothingSelectedDispatchesNoEvent(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('addSelectedExercises');

        $this->assertComponentNotDispatchBrowserEvent($testComponent, 'exercise:selected');
    }

    public function testCloseActionClearsTheSelection(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [$this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY)],
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $testComponent->call('close');

        self::assertSame([], $this->component($testComponent)->selectedIds);
    }

    public function testSelectedExercisesStayCheckedAfterARender(): void
    {
        $client = static::createClient();
        $client->loginUser($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));
        $reverseFlyId = $this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'selectedIds' => [$reverseFlyId],
        ], $client);

        $crawler = $testComponent->render()->crawler();

        self::assertCount(1, $crawler->filter(\sprintf('input[type="checkbox"][value="%s"][checked]', $reverseFlyId)));
    }

    public function testHabitualExercisesAreTheMostFrequentRecentOnesInAlphabeticalOrder(): void
    {
        $user = $this->getUserByEmail(UserFixtures::USER_REVERSE_FLY);
        $reverseFly = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $abWheel = $this->getExerciseByName(self::PUBLIC_AB_WHEEL);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        // Reverse fly plus fréquent, mais affiché après Ab wheel : tri alphabétique, pas par fréquence.
        WorkoutTestHelper::persistPastWorkout($em, $user, $reverseFly, '-2 days', [[10.0, 12]]);
        WorkoutTestHelper::persistPastWorkout($em, $user, $reverseFly, '-4 days', [[10.0, 12]]);
        WorkoutTestHelper::persistPastWorkout($em, $user, $abWheel, '-3 days', [[0.0, 15]]);

        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
        ]);
        $testComponent->actingAs($user);

        $names = array_map(
            static fn (Exercise $exercise): string => $exercise->name,
            $this->component($testComponent)->getHabitualExercises(),
        );

        self::assertSame([self::PUBLIC_AB_WHEEL, ExerciseFixtures::EXERCISE_REVERSE_FLY], $names);
    }

    public function testHabitualExercisesAreHiddenWhileSearching(): void
    {
        $user = $this->getUserByEmail(UserFixtures::USER_REVERSE_FLY);
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        WorkoutTestHelper::persistPastWorkout($em, $user, $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY), '-2 days', [[10.0, 12]]);

        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'search' => 'reverse',
        ]);
        $testComponent->actingAs($user);

        self::assertSame([], $this->component($testComponent)->getHabitualExercises());
    }

    public function testSearchFiltersExercisesByName(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'search' => 'reverse fly',
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $names = array_map(
            static fn ($exercise): string => $exercise->name,
            $this->component($testComponent)->getFilteredExercises()
        );

        self::assertContains(ExerciseFixtures::EXERCISE_REVERSE_FLY, $names);
        self::assertNotContains(ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION, $names);
    }

    public function testSearchWithNoMatchReturnsEmptyResults(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
            'search' => 'this-exercise-does-not-exist-anywhere',
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        self::assertSame([], $this->component($testComponent)->getFilteredExercises());
    }

    /**
     * @return list<string>
     */
    private function dispatchedHtmls(TestLiveComponent $testComponent): array
    {
        $event = $testComponent->getDispatchedBrowserEvent($testComponent->render(), 'exercise:selected');
        self::assertNotNull($event, 'Expected browser event "exercise:selected" to be dispatched.');

        /**
         * Le docblock vendor de `getDispatchedBrowserEvents()` (`array{data: ..., event: ...}`)
         * ne correspond pas à la forme réelle du payload JSON décodé (`payload`, pas `data`,
         * confirmé empiriquement — `AssertDispatchedEvent` du même bundle lit aussi `payload`).
         *
         * @var array{event: string, payload: array{htmls: list<string>}} $event
         */
        return $event['payload']['htmls'];
    }

    private function component(TestLiveComponent $testComponent): ExerciseSelectorComponent
    {
        $component = $testComponent->component();

        self::assertInstanceOf(ExerciseSelectorComponent::class, $component);

        return $component;
    }

    private function getUserByEmail(string $email): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        if (! $user instanceof User) {
            throw new \LogicException(\sprintf('Fixture user "%s" not found.', $email));
        }

        return $user;
    }

    private function getExerciseByName(string $name): Exercise
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $exerciseRepository->findOneBy([
            'name' => $name,
        ]);

        if (null === $exercise) {
            throw new \LogicException(\sprintf('Fixture exercise "%s" not found.', $name));
        }

        return $exercise;
    }

    private function getExerciseIdByName(string $name): string
    {
        return (string) $this->getExerciseByName($name)->id;
    }
}
