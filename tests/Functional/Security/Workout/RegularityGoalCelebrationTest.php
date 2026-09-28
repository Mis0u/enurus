<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\RegularityGoal;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Compte sans séance fixture : la séance soumise est la seule, elle complète à elle seule un défi
 * « 1 séance par semaine pendant 1 semaine » démarré aujourd'hui.
 */
final class RegularityGoalCelebrationTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string CELEBRATION = '[data-regularity-goal-achieved]';

    public function testTheWorkoutThatCompletesTheGoalIsCelebrated(): void
    {
        $client = $this->login(self::USER);
        $this->createOneSessionGoal();

        $this->submitWorkout($client);
        $client->followRedirect();

        $celebrations = json_decode($client->getCrawler()->filter(self::CELEBRATION)->attr('data-goal--achieved-achievements-value') ?? '', true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($celebrations);
        self::assertCount(1, $celebrations);
        $celebration = $celebrations[0];
        self::assertIsArray($celebration);
        self::assertSame('Objectif de régularité atteint !', $celebration['title'] ?? null);
        self::assertIsString($celebration['text'] ?? null);
        self::assertStringContainsString('1 séance par semaine pendant 1 semaine', $celebration['text']);
    }

    public function testAGoalIsCelebratedOnlyOnce(): void
    {
        $client = $this->login(self::USER);
        $this->createOneSessionGoal();

        $this->submitWorkout($client);
        $this->submitWorkout($client);
        $client->followRedirect();

        self::assertSelectorNotExists(self::CELEBRATION);
    }

    private function createOneSessionGoal(): void
    {
        $goal = new RegularityGoal();
        $goal->owner = $this->getUserByEmail(self::USER);
        $goal->period = RegularityGoalPeriodEnum::WEEK;
        $goal->sessionsPerPeriod = 1;
        $goal->duration = RegularityGoalDurationEnum::ONE_WEEK;
        $goal->startDate = new \DateTimeImmutable('today');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($goal);
        $em->flush();
    }

    private function submitWorkout(KernelBrowser $client): void
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        WorkoutTestHelper::submitWorkout($client, $exerciseRepository);
    }
}
