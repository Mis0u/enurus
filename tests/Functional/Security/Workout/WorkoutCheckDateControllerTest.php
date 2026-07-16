<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Repository\ExerciseRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class WorkoutCheckDateControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    public function testCheckDateReturnsExistsWhenWorkoutExists(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/verifier-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        assert(is_string($content));

        $data = json_decode($content, true);
        assert(is_array($data));

        $this->assertTrue($data['exists']);
        $this->assertSame(1, $data['count']);
    }

    public function testCheckDateReturnsNotExistsWhenNoWorkout(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/verifier-date',
            [
                'date' => new \DateTime('+1 day')->format('Y-m-d'),
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        assert(is_string($content));

        $data = json_decode($content, true);
        assert(is_array($data));

        $this->assertFalse($data['exists']);
        $this->assertSame(0, $data['count']);
    }

    public function testCheckDateIsNotAccessibleWhenNotLogged(): void
    {
        $client = static::createClient();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/verifier-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
            ]
        );

        $this->assertResponseRedirects('/fr/');
    }

    public function testCheckDateReturnsCorrectCount(): void
    {
        $client = $this->login(self::USER);

        // Crée 2 séances le même jour
        $this->submitWorkout($client);
        $this->submitWorkout($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/verifier-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
            ]
        );

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        assert(is_string($content));

        $data = json_decode($content, true);
        assert(is_array($data));

        $this->assertTrue($data['exists']);
        $this->assertSame(2, $data['count']);
    }

    private function submitWorkout(KernelBrowser $client): void
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        WorkoutTestHelper::submitWorkout($client, $exerciseRepository);
    }
}
