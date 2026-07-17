<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\Workout;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WorkoutDeleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-26-workout@test.com';

    private const string OTHER_USER = 'user-fixture-11-workout@test.com';

    // -------------------------------------------------------------------------
    // Accès / Sécurité
    // -------------------------------------------------------------------------

    public function testDeleteRedirectsToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(
            Request::METHOD_DELETE,
            $this->getDeleteUrl($workout),
            [],
            [],
            [
                'HTTP_X-Requested-With' => 'XMLHttpRequest',
            ],
        );

        $this->assertResponseRedirects();
    }

    public function testCannotDeleteWorkoutOfAnotherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $workoutOfUser = $this->getFirstWorkout(self::USER);

        $this->deleteRequest($client, $this->getDeleteUrl($workoutOfUser));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteWithInvalidUuidReturns404(): void
    {
        $client = $this->login(self::USER);

        $this->deleteRequest($client, '/fr/seance/invalid-uuid/supprimer');

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testDeleteWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $client->request(Request::METHOD_DELETE, $this->getDeleteUrl($workout));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testDeleteWithInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $this->deleteRequest($client, $this->getDeleteUrl($workout), 'invalid-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // -------------------------------------------------------------------------
    // Suppression
    // -------------------------------------------------------------------------

    public function testDeleteReturnsSuccess(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);

        $this->deleteRequest($client, $this->getDeleteUrl($workout), $this->getDeleteCsrfToken($client, $workout));

        $this->assertResponseIsSuccessful();

        /** @var string $content */
        $content = $client->getResponse()->getContent();
        $this->assertJson($content);

        /** @var array{success: bool} $data */
        $data = json_decode($content, true);
        $this->assertTrue($data['success']);
    }

    public function testWorkoutIsRemovedFromDatabase(): void
    {
        $client = $this->login(self::USER);
        $workout = $this->getFirstWorkout(self::USER);
        $workoutId = $workout->id;

        $this->deleteRequest($client, $this->getDeleteUrl($workout), $this->getDeleteCsrfToken($client, $workout));

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $deleted = $workoutRepository->find($workoutId);

        $this->assertNull($deleted);
    }

    private function getDeleteCsrfToken(KernelBrowser $client, Workout $workout): string
    {
        self::assertNotNull($workout->id);

        return $this->csrfTokenFromPage(
            $client,
            '/fr/mes-seances?limit=50',
            \sprintf('button[data-delete-url*="%s"]', $workout->id->toRfc4122()),
            'data-token',
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

    private function getDeleteUrl(Workout $workout): string
    {
        return \sprintf('/fr/seance/%s/supprimer', $workout->id);
    }
}
