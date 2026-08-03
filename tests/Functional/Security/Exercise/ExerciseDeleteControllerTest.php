<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Repository\ExerciseRepository;
use App\Repository\RoutineExerciseRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExerciseDeleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    // ── Accès / Sécurité ──────────────────────────────────────────────────────

    public function testDeleteRedirectsToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url);

        $this->assertResponseRedirects();
    }

    public function testDeleteWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_DELETE, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testCannotDeleteExerciseOfAnotherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotDeletePublicExercise(): void
    {
        $client = $this->login(self::OWNER);
        $publicExercise = $this->getExerciseByPublicFlag();
        $url = \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $publicExercise->id);

        $this->deleteRequest($client, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteWithInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url, 'invalid-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteReturnsSuccess(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url, $this->getDeleteCsrfToken($client, ExerciseFixtures::EXERCISE_REVERSE_FLY));

        $this->assertResponseIsSuccessful();

        /** @var string $content */
        $content = $client->getResponse()->getContent();
        $this->assertJson($content);

        /** @var array{success: bool} $data */
        $data = json_decode($content, true);
        $this->assertTrue($data['success']);
    }

    public function testExerciseIsRemovedFromDatabase(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $this->assertNotNull($exercise);
        $exerciseId = $exercise->id;

        $this->deleteRequest(
            $client,
            \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $exerciseId),
            $this->getDeleteCsrfToken($client, ExerciseFixtures::EXERCISE_REVERSE_FLY),
        );

        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $this->assertNull($repository->find($exerciseId));
    }

    public function testOtherUserExerciseIsNotAffected(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url, $this->getDeleteCsrfToken($client, ExerciseFixtures::EXERCISE_REVERSE_FLY));

        $remaining = $this->getExerciseByName(ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);
        $this->assertNotNull($remaining);
    }

    // ── Archivage plutôt que suppression ─────────────────────────────────────

    public function testExerciseUsedInWorkoutIsArchivedInsteadOfDeleted(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $this->assertNotNull($exercise);
        $exerciseId = $exercise->id;

        $token = $this->getDeleteCsrfToken($client, ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $em = $this->getEntityManager();
        $owner = $this->getOwnerUser();

        $workout = new Workout();
        $workout->owner = $owner;
        $em->persist($workout);

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->workout = $workout;
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $em->persist($workoutExercise);
        $em->flush();

        $this->deleteRequest(
            $client,
            \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $exerciseId),
            $token,
        );

        $this->assertResponseIsSuccessful();

        /** @var string $content */
        $content = $client->getResponse()->getContent();
        /** @var array{success: bool, archived: bool} $data */
        $data = json_decode($content, true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['archived']);

        $em->clear();

        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $reloaded = $repository->find($exerciseId);
        $this->assertNotNull($reloaded);
        $this->assertTrue($reloaded->archived);
    }

    public function testExerciseUsedOnlyInRoutineIsDeletedAndDetachedFromRoutine(): void
    {
        $client = $this->login(self::OWNER);
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $this->assertNotNull($exercise);
        $exerciseId = $exercise->id;

        $token = $this->getDeleteCsrfToken($client, ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $em = $this->getEntityManager();
        $owner = $this->getOwnerUser();

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Routine de test suppression exercice';
        $em->persist($routine);

        $routineExercise = new RoutineExercise();
        $routineExercise->routine = $routine;
        $routineExercise->exercise = $exercise;
        $routineExercise->position = 0;
        $em->persist($routineExercise);
        $em->flush();
        $routineId = $routine->id;

        $this->deleteRequest(
            $client,
            \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $exerciseId),
            $token,
        );

        $this->assertResponseIsSuccessful();

        /** @var string $content */
        $content = $client->getResponse()->getContent();
        /** @var array{success: bool, archived: bool} $data */
        $data = json_decode($content, true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['archived']);

        $em->clear();

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);
        $this->assertNull($exerciseRepository->find($exerciseId));

        /** @var RoutineExerciseRepository $routineExerciseRepository */
        $routineExerciseRepository = static::getContainer()->get(RoutineExerciseRepository::class);
        $this->assertCount(0, $routineExerciseRepository->findBy([
            'routine' => $routineId,
        ]));
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function getEntityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        return $em;
    }

    private function getOwnerUser(): User
    {
        /** @var UserRepository $userRepo */
        $userRepo = static::getContainer()->get(UserRepository::class);
        $owner = $userRepo->findOneBy([
            'email' => self::OWNER,
        ]);
        $this->assertNotNull($owner);

        return $owner;
    }

    private function getDeleteUrl(string $exerciseName): string
    {
        $exercise = $this->getExerciseByName($exerciseName);
        $this->assertNotNull($exercise);

        return \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $exercise->id);
    }

    private function getDeleteCsrfToken(KernelBrowser $client, string $exerciseName): string
    {
        $exercise = $this->getExerciseByName($exerciseName);
        $this->assertNotNull($exercise);
        $this->assertNotNull($exercise->id);

        return $this->csrfTokenFromPage(
            $client,
            '/fr/bibliotheque',
            \sprintf('div[data-exercise--delete-url-value*="%s"]', $exercise->id->toRfc4122()),
            'data-exercise--delete-csrf-token-value',
        );
    }

    private function getExerciseByName(string $name): ?Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);

        return $repository->findOneBy([
            'name' => $name,
        ]);
    }

    private function getExerciseByPublicFlag(): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $repository->findOneBy([
            'isPublic' => true,
        ]);

        return $exercise;
    }
}
