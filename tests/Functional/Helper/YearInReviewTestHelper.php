<?php

declare(strict_types=1);

namespace App\Tests\Functional\Helper;

use App\Entity\Exercise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final class YearInReviewTestHelper
{
    public static function createVerifiedUser(EntityManagerInterface $em): User
    {
        $user = new User();
        $user->email = \sprintf('year-in-review-test-%s@test.com', uniqid());
        $user->password = 'hashed';
        $user->nickname = 'YearInReviewTestUser';
        $user->locale = 'fr';
        $user->isVerified = true;
        $user->lastLogin = new \DateTimeImmutable();

        $em->persist($user);
        $em->flush();

        return $user;
    }

    public static function createExercise(EntityManagerInterface $em): Exercise
    {
        $exercise = new Exercise();
        $exercise->name = 'Year in review test exercise ' . uniqid();
        $exercise->isPublic = true;

        $em->persist($exercise);
        $em->flush();

        return $exercise;
    }

    /**
     * Une séance de 3 × 10 à 50 kg par date.
     *
     * @param list<string> $performedAts
     */
    public static function persistWorkouts(EntityManagerInterface $em, User $user, Exercise $exercise, array $performedAts): void
    {
        foreach ($performedAts as $performedAt) {
            WorkoutTestHelper::persistPastWorkout($em, $user, $exercise, $performedAt, [[50.0, 10], [50.0, 10], [50.0, 10]]);
        }
    }
}
