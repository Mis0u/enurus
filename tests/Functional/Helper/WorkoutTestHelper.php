<?php

declare(strict_types=1);

namespace App\Tests\Functional\Helper;

use App\Entity\Exercise;
use App\Entity\Workout;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class WorkoutTestHelper
{
    public static function getFirstWorkout(
        UserRepository $userRepository,
        WorkoutRepository $workoutRepository,
        string $email,
    ): Workout {
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        $workouts = $workoutRepository->findBy([
            'owner' => $user,
        ], [
            'id' => 'DESC',
        ]);

        if (! isset($workouts[0])) {
            throw new \LogicException(\sprintf('No workout found for user "%s".', $email));
        }

        return $workouts[0];
    }

    public static function getPublicExerciseId(ExerciseRepository $exerciseRepository): string
    {
        $exercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);

        if (! $exercise instanceof Exercise) {
            throw new \LogicException('No public exercise found in fixtures.');
        }

        return (string) $exercise->id;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    public static function submitWorkout(
        KernelBrowser $client,
        ExerciseRepository $exerciseRepository,
        array $overrides = [],
    ): void {
        $exerciseId = self::getPublicExerciseId($exerciseRepository);

        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');
        $csrfToken = $crawler->filter('input[name="workout[_token]"]')->attr('value');

        $data = array_replace_recursive([
            'workout' => [
                '_token' => $csrfToken,
                'performedAt' => new \DateTime('today')->format('Y-m-d'),
                'duration' => 60,
                'routine' => '',
                'workoutExercises' => [
                    0 => [
                        'exercise' => $exerciseId,
                        'position' => 0,
                        'exerciseSets' => [
                            0 => [
                                'weight' => 80,
                                'reps' => 10,
                                'position' => 0,
                            ],
                        ],
                    ],
                ],
            ],
        ], $overrides);

        $client->request(Request::METHOD_POST, '/fr/enregistre-seance', $data);
    }
}
