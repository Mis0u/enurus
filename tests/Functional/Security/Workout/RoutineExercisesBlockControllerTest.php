<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\DataFixtures\UserFixtures;
use App\Repository\RoutineRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class RoutineExercisesBlockControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ROUTINE_OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string ROUTINE_OTHER = UserFixtures::USER_ROUTINE_OTHER;

    public function testRoutineExercisesBlockRedirectsToLoginWhenNotLogged(): void
    {
        $client = static::createClient();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine',
            [
                'routineId' => '019f0000-0000-7000-8000-000000000000',
            ]
        );

        $this->assertResponseRedirects('/fr/');
    }

    public function testRoutineExercisesBlockReturns400WhenRoutineIdMissing(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);

        $client->request(Request::METHOD_GET, '/fr/enregistre-seance/bloc-exercices-routine');

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRoutineExercisesBlockReturns404WhenRoutineNotFound(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine',
            [
                'routineId' => '019f0000-0000-7000-8000-000000000000',
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testRoutineExercisesBlockReturns403WhenRoutineBelongsToOtherUser(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOtherUserRoutineId();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine',
            [
                'routineId' => $routineId,
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRoutineExercisesBlockReturnsExercisesForOwnRoutine(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOwnerRoutineId($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine',
            [
                'routineId' => $routineId,
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('data-exercise-index', $content);
    }

    public function testRoutineExercisesBlockUsesStartIndex(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOwnerRoutineId($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine',
            [
                'routineId' => $routineId,
                'startIndex' => 5,
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('workout[workoutExercises][5][exercise]', $content);
    }

    public function testRoutineExercisesBlockReturnsCorrectExerciseCount(): void
    {
        $client = $this->login(self::ROUTINE_OWNER);
        $routineId = $this->getOwnerRoutineId($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine',
            [
                'routineId' => $routineId,
            ]
        );

        $this->assertResponseIsSuccessful();

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepository->find($routineId);
        $this->assertNotNull($routine);

        $expectedCount = $routine->routineExercises->count();

        $crawler = $client->getCrawler();
        $blocks = $crawler->filter('[data-exercise-index]');

        $this->assertCount($expectedCount, $blocks);
    }

    private function getOwnerRoutineId(KernelBrowser $client): string
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $owner = $userRepository->findOneBy([
            'email' => self::ROUTINE_OWNER,
        ]);
        $this->assertNotNull($owner);

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepository->findOneBy([
            'owner' => $owner,
            'name' => 'Push Day',
        ]);
        $this->assertNotNull($routine);
        $this->assertNotNull($routine->id);

        return $routine->id->toRfc4122();
    }

    private function getOtherUserRoutineId(): string
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $otherUser = $userRepository->findOneBy([
            'email' => self::ROUTINE_OTHER,
        ]);
        $this->assertNotNull($otherUser);

        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);
        $routine = $routineRepository->findOneBy([
            'owner' => $otherUser,
        ]);
        $this->assertNotNull($routine);
        $this->assertNotNull($routine->id);

        return $routine->id->toRfc4122();
    }
}
