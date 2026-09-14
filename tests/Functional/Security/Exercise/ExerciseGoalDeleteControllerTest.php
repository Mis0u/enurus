<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\ExerciseGoal;
use App\Repository\ExerciseGoalRepository;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExerciseGoalDeleteControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    public function testDeleteWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::OWNER);
        $goal = $this->createGoal($client);

        $client->request(Request::METHOD_DELETE, $this->getDeleteUrl($goal));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testDeleteWithInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::OWNER);
        $goal = $this->createGoal($client);

        $this->deleteRequest($client, $this->getDeleteUrl($goal), 'invalid-token');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testCannotDeleteTheGoalOfAnotherUser(): void
    {
        $client = $this->login(self::OWNER);
        $goal = $this->createGoal($client);
        $token = $this->getDeleteCsrfToken($client, $goal);

        $client->loginUser($this->getUserByEmail(self::OTHER_USER));

        $this->deleteRequest($client, $this->getDeleteUrl($goal), $token);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testDeleteReturnsSuccessAndRemovesTheGoal(): void
    {
        $client = $this->login(self::OWNER);
        $goal = $this->createGoal($client);
        $token = $this->getDeleteCsrfToken($client, $goal);
        $goalId = $goal->id;

        $this->deleteRequest($client, $this->getDeleteUrl($goal), $token);

        $this->assertResponseIsSuccessful();

        /** @var string $content */
        $content = $client->getResponse()->getContent();
        /** @var array{success: bool} $data */
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($data['success']);

        /** @var ExerciseGoalRepository $repository */
        $repository = static::getContainer()->get(ExerciseGoalRepository::class);
        self::assertNull($repository->find($goalId));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createGoal(KernelBrowser $client): ExerciseGoal
    {
        $exercise = $this->getExerciseByName(ExerciseFixtures::EXERCISE_REVERSE_FLY);
        $historyUrl = \sprintf('/fr/bibliotheque/exercice/%s/historique', $exercise->id);

        $crawler = $client->request(Request::METHOD_GET, $historyUrl);
        $form = $crawler->filter('form')->form();
        $form->setValues([
            'exercise_goal[targetWeight]' => '120',
        ]);
        $client->submit($form);

        /** @var ExerciseGoalRepository $repository */
        $repository = static::getContainer()->get(ExerciseGoalRepository::class);
        $goal = $repository->findOneBy([
            'exercise' => $exercise,
        ]);
        self::assertNotNull($goal);

        return $goal;
    }

    private function getDeleteCsrfToken(KernelBrowser $client, ExerciseGoal $goal): string
    {
        $exercise = $goal->exercise;

        return $this->csrfTokenFromPage(
            $client,
            \sprintf('/fr/bibliotheque/exercice/%s/historique', $exercise->id),
            'button[data-exercise--goal-delete-url-value]',
            'data-exercise--goal-delete-csrf-token-value',
        );
    }

    private function getDeleteUrl(ExerciseGoal $goal): string
    {
        return \sprintf('/fr/bibliotheque/objectif/%s/supprimer', $goal->id);
    }

    private function getExerciseByName(string $name): Exercise
    {
        /** @var ExerciseRepository $repository */
        $repository = static::getContainer()->get(ExerciseRepository::class);
        $exercise = $repository->findOneBy([
            'name' => $name,
        ]);
        self::assertNotNull($exercise);

        return $exercise;
    }
}
