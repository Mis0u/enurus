<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Exercise;

use App\DataFixtures\UserFixtures;
use App\Entity\Exercise;
use App\Entity\MuscleGroup;
use App\Entity\User;
use App\Repository\ExerciseRepository;
use App\Repository\MuscleGroupRepository;
use App\Repository\UserRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ExerciseCreateControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = UserFixtures::USER_REVERSE_FLY;

    private const string OTHER_USER = UserFixtures::USER_TIRAGE_SUPINATION;

    private const string URL = '/fr/bibliotheque/exercice/creer';

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER, self::URL, 'Créer un exercice | FitTracker');
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }

    public function testValidExerciseIsPersistedInDatabase(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client);

        $this->assertResponseRedirects('/fr/bibliotheque');

        $exercise = $this->findExercise(self::USER, 'Test Exercise');

        $this->assertNotNull($exercise);
        $this->assertSame('Test Exercise', $exercise->name);
    }

    public function testExerciseOwnerIsCurrentUser(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client);

        /** @var Exercise $exercise */
        $exercise = $this->findExercise(self::USER, 'Test Exercise');

        $this->assertSame(self::USER, $exercise->owner?->email);
    }

    public function testExerciseIsNotPublic(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client);

        /** @var Exercise $exercise */
        $exercise = $this->findExercise(self::USER, 'Test Exercise');

        $this->assertFalse($exercise->isPublic);
    }

    public function testExerciseHasPrimaryMuscle(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client);

        /** @var Exercise $exercise */
        $exercise = $this->findExercise(self::USER, 'Test Exercise');

        $hasPrimary = false;

        foreach ($exercise->exerciseMuscles as $exerciseMuscle) {
            if ('primary' === $exerciseMuscle->type->value) {
                $hasPrimary = true;
                break;
            }
        }

        $this->assertTrue($hasPrimary);
    }

    public function testExerciseWithDescriptionIsPersisted(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client, description: 'Une description technique.');

        /** @var Exercise $exercise */
        $exercise = $this->findExercise(self::USER, 'Test Exercise');

        $this->assertSame('Une description technique.', $exercise->description);
    }

    public function testExerciseWithoutDescriptionHasNullDescription(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client);

        /** @var Exercise $exercise */
        $exercise = $this->findExercise(self::USER, 'Test Exercise');

        $this->assertNull($exercise->description);
    }

    public function testEmptyNameIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client, name: '');

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testEmptyMusclesIsRejected(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client, muscles: '');

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->findExercise(self::USER, 'Test Exercise'));
    }

    public function testMusclesWithoutPrimaryIsRejected(): void
    {
        $client = $this->login(self::USER);
        $muscles = $this->buildMusclesJson(secondaryOnly: true);
        $this->submitExercise($client, muscles: $muscles);

        $this->assertResponseIsSuccessful();
        $this->assertNull($this->findExercise(self::USER, 'Test Exercise'));
    }

    public function testExerciseIsIsolatedFromOtherUser(): void
    {
        $client = $this->login(self::USER);
        $this->submitExercise($client);

        $exercise = $this->findExercise(self::OTHER_USER, 'Test Exercise');

        $this->assertNull($exercise);
    }

    private function submitExercise(
        KernelBrowser $client,
        string $name = 'Test Exercise',
        ?string $muscles = null,
        ?string $description = null,
    ): void {
        $crawler = $client->request(Request::METHOD_GET, self::URL);
        $csrfToken = $crawler->filter('input[name="exercise[_token]"]')->attr('value');

        $client->request(Request::METHOD_POST, self::URL, [
            'exercise' => [
                '_token' => $csrfToken,
                'name' => $name,
                'muscles' => $muscles ?? $this->buildMusclesJson(),
                'description' => $description,
            ],
        ]);
    }

    private function buildMusclesJson(bool $secondaryOnly = false): string
    {
        $muscleGroupId = $this->getMuscleGroupId('name.chest');
        $type = $secondaryOnly ? 'secondary' : 'primary';

        return json_encode([[
            'id' => $muscleGroupId,
            'type' => $type,
        ]], JSON_THROW_ON_ERROR);
    }

    private function getMuscleGroupId(string $name): string
    {
        /** @var MuscleGroupRepository $repository */
        $repository = static::getContainer()->get(MuscleGroupRepository::class);

        /** @var MuscleGroup $muscleGroup */
        $muscleGroup = $repository->findOneBy([
            'name' => $name,
        ]);

        return (string) $muscleGroup->id;
    }

    private function findExercise(string $userEmail, string $exerciseName): ?Exercise
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);

        /** @var User $user */
        $user = $userRepository->findOneBy([
            'email' => $userEmail,
        ]);

        /** @var ExerciseRepository $exerciseRepository */
        $exerciseRepository = static::getContainer()->get(ExerciseRepository::class);

        return $exerciseRepository->findOneBy([
            'owner' => $user,
            'name' => $exerciseName,
        ]);
    }
}
