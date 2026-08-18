<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Routine;

use App\DataFixtures\UserFixtures;
use App\Entity\Routine;
use App\Repository\RoutineRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RoutineShowControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string OTHER_USER = UserFixtures::USER_ROUTINE_OTHER;

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $client = static::createClient();
        $url = $this->getShowUrl('Push Day');

        $this->assertPageIsRedirectToLoginWhenNotLogged($url, $client);
    }

    public function testIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getShowUrl('Push Day');

        $this->assertPageIsAccessibleWhenLogged(self::OWNER, $url, 'Push day | Enurus', $client);
    }

    public function testOtherUserCannotViewSomeoneElsesRoutine(): void
    {
        $client = $this->login(self::OTHER_USER);
        $url = $this->getShowUrl('Push Day');

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testShowsTheRoutineName(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getShowUrl('Push Day');

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('header h1', 'Push Day');
    }

    public function testShowsTheExercisesOfTheRoutine(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getShowUrl('Push Day');
        $routine = $this->getRoutineByName('Push Day');

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
        self::assertCount(
            $routine->routineExercises->count(),
            $client->getCrawler()->filter('.routine-show-exercise-tile'),
        );
    }

    public function testEmptyUsageStateIsDisplayedWhenRoutineNeverUsed(): void
    {
        $client = $this->login(self::OWNER);
        $url = $this->getShowUrl('Push Day');

        $client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', "Cette routine n'a pas encore été utilisée dans une séance.");
    }

    private function getShowUrl(string $name): string
    {
        $routine = $this->getRoutineByName($name);

        return \sprintf('/fr/mes-routines/%s', $routine->id);
    }

    private function getRoutineByName(string $name): Routine
    {
        /** @var RoutineRepository $routineRepository */
        $routineRepository = static::getContainer()->get(RoutineRepository::class);

        /** @var Routine $routine */
        $routine = $routineRepository->findOneBy([
            'name' => $name,
        ]);

        return $routine;
    }
}
