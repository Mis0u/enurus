<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service\YearInReview;

use App\Entity\YearInReview;
use App\Service\YearInReview\YearInReviewGenerator;
use App\Tests\Functional\Helper\YearInReviewTestHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class YearInReviewGeneratorTest extends KernelTestCase
{
    private const array WORKOUTS_AFTER_THE_PERIOD = ['2026-12-16 00:00:00', '2026-12-18 10:00:00'];

    public function testFourWorkoutsInThePeriodAreNotEnoughEvenWithLaterOnes(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = YearInReviewTestHelper::createVerifiedUser($em);
        YearInReviewTestHelper::persistWorkouts($em, $user, YearInReviewTestHelper::createExercise($em), [
            '2026-01-01 00:00:00', '2026-04-10 10:00:00', '2026-08-20 10:00:00', '2026-12-15 23:59:00',
            ...self::WORKOUTS_AFTER_THE_PERIOD,
        ]);

        $review = $this->generator()->generate($user, 2026);

        self::assertNotNull($review);
        self::assertFalse($review->isEligible());
        self::assertSame(4, $review->workoutCount);
        self::assertSame(1, $review->missingWorkoutCount());
    }

    public function testFiveWorkoutsInThePeriodGiveAFullSnapshot(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = YearInReviewTestHelper::createVerifiedUser($em);
        $exercise = YearInReviewTestHelper::createExercise($em);
        YearInReviewTestHelper::persistWorkouts($em, $user, $exercise, [
            '2026-01-05 10:00:00', '2026-01-12 10:00:00', '2026-01-19 10:00:00', '2026-03-02 10:00:00', '2026-12-15 23:59:00',
            ...self::WORKOUTS_AFTER_THE_PERIOD,
        ]);

        $snapshot = $this->generator()->generate($user, 2026)?->snapshot();

        self::assertNotNull($snapshot);
        self::assertSame(5, $snapshot->totals->workoutCount);
        self::assertSame(15, $snapshot->totals->setCount);
        self::assertSame(150, $snapshot->totals->repCount);
        self::assertSame(7500.0, $snapshot->totals->tonnageKg);
        self::assertCount(5, $snapshot->tonnageKgByDay);
        self::assertSame($exercise->name, $snapshot->topExercises[0]->name);
        self::assertSame(5, $snapshot->topExercises[0]->workoutCount);
        self::assertSame(3, $snapshot->regularity->longestStreakWeeks);
        self::assertSame(1, $snapshot->regularity->busiestMonth);
        self::assertSame(50.0, $snapshot->records->heaviestSet?->weightKg);
        self::assertNull($snapshot->dominantMood);
    }

    public function testSecondGenerationKeepsTheFrozenReview(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $generator = $this->generator();
        $user = YearInReviewTestHelper::createVerifiedUser($em);
        $exercise = YearInReviewTestHelper::createExercise($em);
        YearInReviewTestHelper::persistWorkouts($em, $user, $exercise, ['2026-03-02 10:00:00', '2026-03-04 10:00:00']);

        $firstReview = $generator->generate($user, 2026);
        // Séances saisies après la publication : ne doivent jamais modifier le résumé figé.
        YearInReviewTestHelper::persistWorkouts($em, $user, $exercise, ['2026-05-02 10:00:00', '2026-05-04 10:00:00', '2026-05-06 10:00:00']);
        $secondReview = $generator->generate($user, 2026);

        self::assertInstanceOf(YearInReview::class, $firstReview);
        self::assertNull($secondReview);
        $storedReviews = $em->getRepository(YearInReview::class)->findBy([
            'owner' => $user,
        ]);
        self::assertCount(1, $storedReviews);
        self::assertSame(2, $storedReviews[0]->workoutCount);
    }

    private function generator(): YearInReviewGenerator
    {
        /** @var YearInReviewGenerator */
        return static::getContainer()->get(YearInReviewGenerator::class);
    }
}
