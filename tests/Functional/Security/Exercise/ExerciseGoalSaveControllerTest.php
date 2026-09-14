<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\ExerciseGoalRepository;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExerciseGoalSaveControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    public function testCreatesAGoalWhenNoneExists(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitGoalForm($client, $exercise, '120');

        $this->assertResponseRedirects($this->getHistoryUrl($exercise));

        $goals = $this->getGoalsFor($exercise);
        self::assertCount(1, $goals);
        self::assertSame(120.0, $goals[0]->targetWeight);
    }

    public function testEditingAnActiveGoalUpdatesItInPlaceRatherThanCreatingASecondRow(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitGoalForm($client, $exercise, '120');
        $this->submitGoalForm($client, $exercise, '150');

        $goals = $this->getGoalsFor($exercise);
        self::assertCount(1, $goals);
        self::assertSame(150.0, $goals[0]->targetWeight);
    }

    public function testANewGoalCanBeCreatedAfterThePreviousOneWasAchieved(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitGoalForm($client, $exercise, '100');

        // Le client reboote le kernel à chaque requête : refetch owner/exercise via le conteneur
        // courant avant de les utiliser côté EntityManager, pour rester dans le même contexte
        // Doctrine (sinon "unmanaged entity" en mixant des entités de deux EM différents).
        $freshExercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $freshOwner = $this->getUserEntityByEmail(self::OWNER);
        $this->recordWorkoutSet($freshOwner, $freshExercise, weight: 110.0);

        $this->submitGoalForm($client, $freshExercise, '150');

        $goals = $this->getGoalsFor($exercise);
        self::assertCount(2, $goals, 'the achieved goal must stay in history instead of being overwritten');

        $targets = array_map(static fn ($goal) => $goal->targetWeight, $goals);
        sort($targets);
        self::assertSame([100.0, 150.0], $targets);
    }

    public function testCreatesADualTargetGoalWithReps(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $crawler = $client->request(Request::METHOD_GET, $this->getHistoryUrl($exercise));
        $form = $crawler->filter('form')->form();
        $form->setValues([
            'exercise_goal[targetWeight]' => '100',
            'exercise_goal[targetReps]' => '6',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects($this->getHistoryUrl($exercise));

        $goals = $this->getGoalsFor($exercise);
        self::assertCount(1, $goals);
        self::assertSame(100.0, $goals[0]->targetWeight);
        self::assertSame(6, $goals[0]->targetReps);
    }

    public function testTargetRepsRemainsOptionalOnAWeightRepsGoal(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitGoalForm($client, $exercise, '120');

        $goals = $this->getGoalsFor($exercise);
        self::assertCount(1, $goals);
        self::assertNull($goals[0]->targetReps);
    }

    public function testSavingAGoalAlreadyMetByAnExistingRecordIsNotPersistedAndShowsTheAlreadyAchievedPopup(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $this->recordWorkoutSet($this->getUserEntityByEmail(self::OWNER), $exercise, weight: 150.0);

        $this->submitGoalForm($client, $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY), '100');
        $client->followRedirect();

        self::assertCount(0, $this->getGoalsFor($exercise), 'an already-met target must never be persisted, to avoid duplicate history entries');
        self::assertSelectorExists('[data-controller="goal--already-achieved"]');
        self::assertSelectorNotExists('[data-controller="goal--achieved"]');
    }

    public function testSubmittingTheSameAlreadyMetTargetTwiceNeverCreatesDuplicateHistoryEntries(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $this->recordWorkoutSet($this->getUserEntityByEmail(self::OWNER), $exercise, weight: 150.0);

        $this->submitGoalForm($client, $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY), '100');
        $this->submitGoalForm($client, $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY), '100');
        $this->submitGoalForm($client, $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY), '100');

        self::assertCount(0, $this->getGoalsFor($exercise));
    }

    public function testSavingAGoalNotYetMetDoesNotTriggerAnyAchievementPopup(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitGoalForm($client, $exercise, '999');
        $client->followRedirect();

        self::assertSelectorNotExists('[data-controller="goal--achieved"]');
        self::assertSelectorNotExists('[data-controller="goal--already-achieved"]');
        self::assertCount(1, $this->getGoalsFor($exercise));
    }

    public function testInvalidSubmissionDoesNotPersistAnything(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->submitGoalForm($client, $exercise, '0');

        $this->assertResponseRedirects($this->getHistoryUrl($exercise));
        self::assertCount(0, $this->getGoalsFor($exercise));

        $client->followRedirect();
        $this->assertSelectorTextContains('body', "L'objectif n'a pas pu être enregistré");
    }

    public function testCannotDefineAGoalOnABodyweightExerciseWithoutBodyweightSet(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getBodyweightExercise();

        $client->request(Request::METHOD_POST, $this->getSaveUrl($exercise), [
            'exercise_goal' => [
                'targetWeight' => '20',
            ],
        ]);

        $this->assertResponseRedirects($this->getHistoryUrl($exercise));
        self::assertCount(0, $this->getGoalsFor($exercise));

        $client->followRedirect();
        $this->assertSelectorTextContains('body', 'Renseigne ton poids en réglages');
    }

    public function testCanDefineAGoalOnABodyweightExerciseOnceBodyweightIsSet(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getBodyweightExercise();
        $owner = $this->getUserEntityByEmail(self::OWNER);
        $owner->bodyweightKg = 80.0;
        $this->getEntityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, $this->getHistoryUrl($exercise));
        $form = $crawler->filter('form')->form();
        $form->setValues([
            'exercise_goal[targetWeight]' => '20',
        ]);
        $client->submit($form);

        $this->assertResponseRedirects($this->getHistoryUrl($exercise));
        $goals = $this->getGoalsFor($exercise);
        self::assertCount(1, $goals);
        // Le poids de corps (80kg) n'est jamais ajouté à la cible : elle reste 20kg de lest.
        self::assertSame(20.0, $goals[0]->targetWeight);
    }

    public function testCannotSaveAGoalOnAnExerciseNotVisibleToTheUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_POST, $this->getSaveUrl($exercise), [
            'exercise_goal' => [
                'targetWeight' => '120',
            ],
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testEachUserKeepsAnIndependentGoalOnASharedPublicExercise(): void
    {
        // Reachable only through a public exercise (shared between users) — the voter check on
        // ExerciseGoalVoter::EDIT (goal ownership) is otherwise unreachable, since ExerciseVoter::VIEW
        // already blocks a private exercise before the goal voter is even evaluated (covered by
        // testCannotSaveAGoalOnAnExerciseNotVisibleToTheUser).
        $client = $this->login(self::OTHER_USER);
        $publicExercise = $this->getPublicExercise();
        $this->submitGoalForm($client, $publicExercise, '50');

        $client->loginUser($this->getUserEntityByEmail(self::OWNER));
        $this->submitGoalForm($client, $publicExercise, '999');

        $goals = $this->getGoalsFor($publicExercise);
        self::assertCount(2, $goals, 'each user must keep their own independent goal on a shared public exercise');
    }

    public function testRouteIsLocalePrefixedInEnglish(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_POST, \sprintf('/en/library/exercise/%s/goal', $exercise->id), [
            'exercise_goal' => [
                'targetWeight' => '120',
            ],
        ]);

        $this->assertResponseRedirects(\sprintf('/en/library/exercise/%s/history', $exercise->id));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function submitGoalForm(KernelBrowser $client, Exercise $exercise, string $targetWeight): void
    {
        $crawler = $client->request(Request::METHOD_GET, $this->getHistoryUrl($exercise));
        $form = $crawler->filter('form')->form();
        $form->setValues([
            'exercise_goal[targetWeight]' => $targetWeight,
        ]);

        $client->submit($form);
    }

    private function recordWorkoutSet(User $owner, Exercise $exercise, float $weight): void
    {
        $em = $this->getEntityManager();

        $workout = new Workout();
        $workout->owner = $owner;
        $workout->performedAt = new \DateTimeImmutable('yesterday');
        $em->persist($workout);

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->workout = $workout;
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $em->persist($workoutExercise);

        $set = new ExerciseSet();
        $set->workoutExercise = $workoutExercise;
        $set->position = 0;
        $set->weight = $weight;
        $set->reps = 5;
        $em->persist($set);

        $em->flush();
    }

    /**
     * @return array<\App\Entity\ExerciseGoal>
     */
    private function getGoalsFor(Exercise $exercise): array
    {
        /** @var ExerciseGoalRepository $repository */
        $repository = static::getContainer()->get(ExerciseGoalRepository::class);

        return $repository->findBy([
            'exercise' => $exercise,
        ]);
    }

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }

    private function getUserEntityByEmail(string $email): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);
        self::assertNotNull($user);

        return $user;
    }

    private function getHistoryUrl(Exercise $exercise): string
    {
        return \sprintf('/fr/bibliotheque/exercice/%s/historique', $exercise->id);
    }

    private function getSaveUrl(Exercise $exercise): string
    {
        return \sprintf('/fr/bibliotheque/exercice/%s/objectif', $exercise->id);
    }

    private function getExerciseByName(string $name): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $repository->findOneBy([
            'name' => $name,
        ]);
        self::assertNotNull($exercise);

        return $exercise;
    }

    private function getPublicExercise(): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $repository->findOneBy([
            'isPublic' => true,
        ]);
        self::assertNotNull($exercise);

        return $exercise;
    }

    private function getBodyweightExercise(): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $repository->findOneBy([
            'name' => 'dips_chest.name',
        ]);
        self::assertNotNull($exercise, 'Fixture bodyweight exercise "dips_chest.name" not found.');

        return $exercise;
    }
}
