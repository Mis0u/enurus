<?php

declare(strict_types=1);

namespace App\Tests\Functional\Twig\Components\LiveComponent;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Twig\Components\LiveComponent\ExerciseSelectorComponent;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class ExerciseSelectorComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    private const string COMPONENT_NAME = 'LiveComponent:ExerciseSelectorComponent:ExerciseSelectorComponent';

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

        self::assertGreaterThan(0, $crawler->filter('[data-live-action-param="selectExercise"]')->count());
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

    public function testSelectExerciseClosesModalAndDispatchesBrowserEvent(): void
    {
        $testComponent = $this->createLiveComponent(self::COMPONENT_NAME, [
            'isOpen' => true,
        ]);
        $testComponent->actingAs($this->getUserByEmail(UserFixtures::USER_REVERSE_FLY));

        $exerciseId = $this->getExerciseIdByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $testComponent->call('selectExercise', [
            'id' => $exerciseId,
        ]);

        $component = $this->component($testComponent);
        self::assertFalse($component->isOpen);
        self::assertSame('', $component->search);

        $this->assertComponentDispatchBrowserEvent($testComponent, 'exercise:selected')
            ->withPayload([
                'id' => $exerciseId,
            ]);
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

    private function getExerciseIdByName(string $name): string
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $exerciseRepository->findOneBy([
            'name' => $name,
        ]);

        if (null === $exercise) {
            throw new \LogicException(\sprintf('Fixture exercise "%s" not found.', $name));
        }

        return (string) $exercise->id;
    }
}
