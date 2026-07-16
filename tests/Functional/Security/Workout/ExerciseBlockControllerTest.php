<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\Exercise;
use App\Repository\ExerciseRepository;
use App\Tests\Functional\Helper\WorkoutTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class ExerciseBlockControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    public function testExerciseBlockIsAccessibleWhenLogged(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
    }

    public function testExerciseBlockRedirectsToLoginWhenNotLogged(): void
    {
        $client = static::createClient();
        $exerciseId = $this->getExerciseId();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseRedirects('/fr/');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h2', 'Connexion');
    }

    public function testExerciseBlockReturnsHtml(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/html; charset=UTF-8');
    }

    public function testExerciseBlockWithInvalidIdReturnsNotFound(): void
    {
        $client = $this->login(self::USER);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => '00000000-0000-0000-0000-000000000000',
                'index' => 0,
            ]
        );

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testExerciseBlockContainsCorrectInputNames(): void
    {
        $client = $this->login(self::USER);
        $exerciseId = $this->getExerciseId();

        $crawler = $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => $exerciseId,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][exercise]"]'));
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][exerciseSets][0][weight]"]'));
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][exerciseSets][0][reps]"]'));
        $this->assertCount(1, $crawler->filter('input[name="workout[workoutExercises][0][position]"]'));
    }

    public function testExerciseBlockContainsTranslatedExerciseName(): void
    {
        $client = $this->login(self::USER);

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => (string) $exercise->id,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();

        /** @var TranslatorInterface $translator */
        $translator = static::getContainer()->get(TranslatorInterface::class);
        $translatedName = $translator->trans($exercise->name, [], 'exercise', 'fr');

        $this->assertSelectorTextContains('h4', $translatedName);
    }

    public function testExerciseBlockContainsMusclesTags(): void
    {
        $client = $this->login(self::USER);

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        /** @var Exercise $exercise */
        $exercise = $exerciseRepository->findOneBy([
            'isPublic' => true,
        ]);

        $crawler = $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercice',
            [
                'exerciseId' => (string) $exercise->id,
                'index' => 0,
            ]
        );

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.muscle-tag, span[class*="text-[#f43f5e]"], span[class*="text-[#a855f7]"]')->count());
    }

    private function getExerciseId(): string
    {
        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        return WorkoutTestHelper::getPublicExerciseId($exerciseRepository);
    }
}
