<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\User;
use App\Entity\Workout;
use App\Repository\ExerciseRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
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

    public function testCheckDateExcludesTheWorkoutBeingEdited(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);
        $workoutId = $this->getLatestWorkoutId();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/verifier-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
                'excludeId' => $workoutId,
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

    public function testCheckDateStillDetectsOtherWorkoutsWhenExcluding(): void
    {
        $client = $this->login(self::USER);
        $this->submitWorkout($client);
        $workoutId = $this->getLatestWorkoutId();
        $this->submitWorkout($client);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/verifier-date',
            [
                'date' => new \DateTime('today')->format('Y-m-d'),
                'excludeId' => $workoutId,
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

    private function submitWorkout(KernelBrowser $client): void
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        WorkoutTestHelper::submitWorkout($client, $exerciseRepository);
    }

    private function getLatestWorkoutId(): string
    {
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        $user = $userRepository->findOneBy([
            'email' => self::USER,
        ]);
        assert($user instanceof User);

        $workout = $em->getRepository(Workout::class)->findOneBy(
            [
                'owner' => $user,
            ],
            [
                'id' => 'DESC',
            ],
        );
        assert($workout instanceof Workout);
        assert(null !== $workout->id);

        return $workout->id->toRfc4122();
    }
}
