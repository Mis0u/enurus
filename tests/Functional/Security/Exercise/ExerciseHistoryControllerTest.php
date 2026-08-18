<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\ExerciseFixtures;
use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ExerciseHistoryControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    public function testIsAccessibleWhenLogged(): void
    {
        $client = static::createClient();
        $url = $this->getHistoryUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => self::OWNER,
        ]);
        $client->loginUser($user);

        $this->assertPageIsAccessibleWhenLogged(self::OWNER, $url, 'Reverse fly | Enurus', $client);
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $url = $this->getHistoryUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $this->assertPageIsRedirectToLoginWhenNotLogged($url, $client);
    }

    public function testOtherUserCannotViewSomeoneElsesPrivateExercise(): void
    {
        $client = $this->login(self::OTHER_USER);
        $url = $this->getHistoryUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testAnyLoggedUserCanViewAPublicExercise(): void
    {
        $client = $this->login(self::OWNER);
        $publicExercise = $this->getExerciseByPublicFlag();
        $url = \sprintf('/fr/bibliotheque/exercice/%s/historique', $publicExercise->id);

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
    }

    public function testExerciseWithNoRecordedSessionShowsTheEmptyState(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getHistoryUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Aucune séance enregistrée pour cet exercice.');
    }

    public function testShowsTheBodyweightBadgeNextToTheTitleWhenExerciseIsBodyweight(): void
    {
        $client = $this->login(self::OWNER);

        $exercise = $this->getExerciseByName('dips_chest.name');
        $this->assertNotNull($exercise);

        $client->request(Request::METHOD_GET, \sprintf('/fr/bibliotheque/exercice/%s/historique', $exercise->id));

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('header h1', 'PDC 92%');
    }

    public function testDoesNotShowTheBodyweightBadgeWhenExerciseIsNotBodyweight(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getHistoryUrl(ExerciseFixtures::EXERCISE_REVERSE_FLY);

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorNotExists('header h1 span');
    }

    private function getHistoryUrl(string $exerciseName): string
    {
        $exercise = $this->getExerciseByName($exerciseName);
        $this->assertNotNull($exercise);

        return \sprintf('/fr/bibliotheque/exercice/%s/historique', $exercise->id);
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
