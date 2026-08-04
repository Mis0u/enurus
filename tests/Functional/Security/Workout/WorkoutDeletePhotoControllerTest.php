<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\Workout;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkoutDeletePhotoControllerTest extends WebTestCase
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
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_DELETE, $this->getDeleteUrl($workout), server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseRedirects();
    }

    public function testReturnsForbiddenWhenNotOwner(): void
    {
        $client = $this->login(self::OTHER_USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_DELETE, $this->getDeleteUrl($workout), server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_DELETE, $this->getDeleteUrl($workout));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testWithInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $this->deleteRequest($client, $this->getDeleteUrl($workout), 'invalid-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // Suppression
    // -------------------------------------------------------------------------

    public function testDeleteClearsPhotoPathOnWorkout(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_POST, $this->getUploadUrl($workout), [], [
            'photo' => ImageTestHelper::createFakeImage('photo.jpg', 'image/jpeg'),
        ], [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);
        $this->assertResponseIsSuccessful();

        $this->deleteRequest($client, $this->getDeleteUrl($workout), $this->getDeleteCsrfToken($client, $workout));
        $this->assertResponseIsSuccessful();

        $updated = $this->findUpdatedWorkout($workout->id);
        $this->assertNull($updated->photoPath);
    }

    public function testDeleteIsIdempotentWhenNoPhotoExists(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $this->deleteRequest($client, $this->getDeleteUrl($workout), $this->getDeleteCsrfToken($client, $workout));

        $this->assertResponseIsSuccessful();
    }

    // -------------------------------------------------------------------------
    // Helpers privés
    // -------------------------------------------------------------------------

    private function deleteRequest(KernelBrowser $client, string $url, string $token): void
    {
        $client->request(Request::METHOD_DELETE, $url, server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_X-CSRF-Token' => $token,
        ]);
    }

    private function getDeleteCsrfToken(KernelBrowser $client, Workout $workout): string
    {
        $this->assertNotNull($workout->id);

        return $this->csrfTokenFromPage(
            $client,
            $this->getEditUrl($workout),
            'div[data-workout--photo-upload-delete-url-value]',
            'data-workout--photo-upload-delete-csrf-token-value',
        );
    }

    private function getFirstWorkout(string $email): Workout
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);

        return WorkoutTestHelper::getFirstWorkout($userRepository, $workoutRepository, $email);
    }

    private function getUploadUrl(Workout $workout): string
    {
        return \sprintf('/fr/seance/%s/photo', $workout->id);
    }

    private function getDeleteUrl(Workout $workout): string
    {
        return \sprintf('/fr/seance/%s/photo', $workout->id);
    }

    private function getEditUrl(Workout $workout): string
    {
        return \sprintf('/fr/seance/%s/modifier', $workout->id);
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
