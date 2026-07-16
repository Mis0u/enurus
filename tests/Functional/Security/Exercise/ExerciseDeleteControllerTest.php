<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
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

    // ── Suppression ───────────────────────────────────────────────────────────

    public function testDeleteReturnsSuccess(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url);

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

        $this->deleteRequest($client, \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $exerciseId));

        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $this->assertNull($repository->find($exerciseId));
    }

    public function testOtherUserExerciseIsNotAffected(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getDeleteUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->deleteRequest($client, $url);

        $remaining = $this->getExerciseByName(ExerciseFixtures::EXERCISE_TIRAGE_SUPINATION);
        $this->assertNotNull($remaining);
    }

    // ── Helpers privés ────────────────────────────────────────────────────────

    private function getDeleteUrl(string $exerciseName): string
    {
        $exercise = $this->getExerciseByName($exerciseName);
        $this->assertNotNull($exercise);

        return \sprintf('/fr/bibliotheque/exercice/%s/supprimer', $exercise->id);
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
