<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\Workout;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkoutUploadPhotoControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-26-workout@test.com';

    private const string OTHER_USER = 'user-fixture-11-workout@test.com';

    // -------------------------------------------------------------------------
    // Sécurité / Accès
    // -------------------------------------------------------------------------

    public function testRedirectsToLoginWhenNotLogged(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $workouts = $workoutRepository->findBy(
            [
                'owner' => $user,
            ],
            [
                'performedAt' => 'DESC',
            ],
        );

        $url = \sprintf('/fr/workout/%s/photo', $workouts[0]->id);

        $client->request(Request::METHOD_POST, $url, [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseRedirects();
    }

    public function testReturnsForbiddenWhenNotOwner(): void
    {
        $client = $this->login(self::OTHER_USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // Validation
    // -------------------------------------------------------------------------

    public function testReturnsBadRequestWhenNoFile(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRejectsInvalidMimeType(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createFakeImage('document.pdf', 'application/pdf'),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testRejectsFileTooLarge(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createLargeJpeg(),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    // -------------------------------------------------------------------------
    // Upload réussi
    // -------------------------------------------------------------------------

    public function testUploadSucceedsWithValidJpeg(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createFakeImage('photo.jpg', 'image/jpeg'),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertNotFalse($content);

        /** @var array{path: string, url: string} $response */
        $response = json_decode($content, true);
        $this->assertArrayHasKey('path', $response);
        $this->assertArrayHasKey('url', $response);
        $this->assertStringStartsWith('workouts/', $response['path']);
    }

    public function testUploadPersistsPhotoPathOnWorkout(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createFakeImage('photo.jpg', 'image/jpeg'),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $updated = $this->findUpdatedWorkout($workout->id);
        $this->assertNotNull($updated->photoPath);
        $this->assertStringStartsWith('workouts/', $updated->photoPath);
    }

    public function testUploadReplacesExistingPhoto(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createFakeImage('first.jpg', 'image/jpeg'),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $firstPath = $this->findUpdatedWorkout($workout->id)->photoPath;

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createFakeImage('second.jpg', 'image/jpeg'),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $secondPath = $this->findUpdatedWorkout($workout->id)->photoPath;

        $this->assertNotSame($firstPath, $secondPath);
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private function getFirstWorkout(string $email): Workout
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy([
            'email' => $email,
        ]);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $workouts = $workoutRepository->findBy(
            [
                'owner' => $user,
            ],
            [
                'performedAt' => 'DESC',
            ],
        );

        /** @var Workout $workout */
        $workout = $workouts[0];

        return $workout;
    }

    private function getUploadUrl(Workout $workout): string
    {
        return \sprintf('/fr/workout/%s/photo', $workout->id);
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
}
