<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\WorkoutCrudController;
use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Entity\WorkoutExercise;
use App\Service\Utils\ImageUploadService;
use App\Tests\Functional\Helper\ImageTestHelper;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class WorkoutCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexListsWorkout(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-index@test.com');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(WorkoutCrudController::class)
            ->setAction('index')
            ->set('filters[owner][comparison]', '=')
            ->set('filters[owner][value]', (string) $owner->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'workout-crud-index@test.com');
        // `^=` (starts-with) et non une égalité stricte : la page index filtrée ajoute la query
        // string du filtre actif à chaque lien d'action (préservée pour le retour à la liste).
        self::assertSelectorExists('a[href^="' . $this->actionUrl($client, $workout, 'detail') . '"]');

        $this->cleanup($owner, $workout);
    }

    public function testDetailShowsNoteAndPhoto(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-detail@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ImageUploadService $imageUploadService */
        $imageUploadService = static::getContainer()->get(ImageUploadService::class);

        $workout->note = 'Séance difficile aujourd\'hui.';
        $workout->photoPath = $imageUploadService->upload(
            ImageTestHelper::createFakeImage('workout-detail.jpg', 'image/jpeg'),
            'workouts',
            (string) $owner->id,
        );
        $entityManager->flush();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $workout, 'detail'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Séance difficile aujourd\'hui.');
        self::assertSelectorExists('img[src="/uploads/' . $workout->photoPath . '"]');

        $imageUploadService->delete($workout->photoPath);
        $this->cleanup($owner, $workout);
    }

    public function testEditIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-edit@test.com');

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $workout, 'edit'));

        self::assertResponseStatusCodeSame(403);

        $this->cleanup($owner, $workout);
    }

    public function testDetailShowsComposingExercises(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-exercises@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->name = 'Rowing barre';
        $entityManager->persist($exercise);

        $workoutExercise = new WorkoutExercise();
        $workoutExercise->workout = $workout;
        $workoutExercise->exercise = $exercise;
        $workoutExercise->position = 0;
        $entityManager->persist($workoutExercise);
        $entityManager->flush();
        // `clear()` : le client de test ne rebootant pas systématiquement le kernel entre deux
        // appels, `$workout` resterait sinon le même objet PHP dont `workoutExercises` est
        // l'ArrayCollection vide posée par le constructeur, jamais réhydratée depuis la base.
        $entityManager->clear();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $workout, 'detail'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Rowing barre');

        /** @var WorkoutExercise $reloadedWorkoutExercise */
        $reloadedWorkoutExercise = $entityManager->getRepository(WorkoutExercise::class)->find($workoutExercise->id);
        /** @var Exercise $reloadedExercise */
        $reloadedExercise = $entityManager->getRepository(Exercise::class)->find($exercise->id);
        $entityManager->remove($reloadedWorkoutExercise);
        $entityManager->remove($reloadedExercise);
        $entityManager->flush();
        $this->cleanup($owner, $workout);
    }

    public function testDeleteRemovesWorkoutAndPhotoFile(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-delete@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        /** @var ImageUploadService $imageUploadService */
        $imageUploadService = static::getContainer()->get(ImageUploadService::class);

        $workout->photoPath = $imageUploadService->upload(
            ImageTestHelper::createFakeImage('workout-delete.jpg', 'image/jpeg'),
            'workouts',
            (string) $owner->id,
        );
        $entityManager->flush();
        $photoFilesystemPath = __DIR__ . '/../../../public/uploads/' . $workout->photoPath;
        self::assertFileExists($photoFilesystemPath);

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $workout, 'detail'),
            'input[name="token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $workout, 'delete'), [
            'token' => $token,
        ]);

        self::assertResponseRedirects();
        self::assertNull($entityManager->getRepository(Workout::class)->find($workout->id));
        self::assertFileDoesNotExist($photoFilesystemPath);

        $this->deleteTestUser($owner);
    }

    public function testRoutineFieldLinksToRoutineCrudController(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-routine@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Routine liée';
        $entityManager->persist($routine);
        $workout->routine = $routine;
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $routineDetailUrl = $adminUrlGenerator
            ->setController(\App\Controller\Admin\RoutineCrudController::class)
            ->setAction('detail')
            ->setEntityId($routine->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $workout, 'detail'));

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="' . $routineDetailUrl . '"]');

        $entityManager->remove($routine);
        $this->cleanup($owner, $workout);
    }

    public function testRoutineFilterNarrowsIndexToMatchingRoutine(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $workout] = $this->createWorkout('workout-crud-routine-filter@test.com');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = 'Routine filtrante';
        $entityManager->persist($routine);
        $workout->routine = $routine;
        $entityManager->flush();

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(WorkoutCrudController::class)
            ->setAction('index')
            ->set('filters[routine][comparison]', '=')
            ->set('filters[routine][value]', (string) $routine->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'workout-crud-routine-filter@test.com');

        $entityManager->remove($routine);
        $this->cleanup($owner, $workout);
    }

    public function testFiltersRenderWithoutError(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator->setController(WorkoutCrudController::class)->setAction('renderFilters')->generateUrl();

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return array{0: User, 1: Workout}
     */
    private function createWorkout(string $email): array
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $owner = new User();
        $owner->email = $email;
        $owner->password = 'hashed';
        $owner->nickname = 'T' . substr(bin2hex(random_bytes(8)), 0, 16);
        $owner->lastLogin = new \DateTimeImmutable();
        $owner->locale = 'fr';
        $entityManager->persist($owner);

        $workout = new Workout();
        $workout->owner = $owner;
        $workout->performedAt = new \DateTimeImmutable('2026-01-15');
        $entityManager->persist($workout);

        $entityManager->flush();

        return [$owner, $workout];
    }

    private function cleanup(User $owner, Workout $workout): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $reloadedWorkout = $entityManager->getRepository(Workout::class)->find($workout->id);

        if ($reloadedWorkout instanceof Workout) {
            $entityManager->remove($reloadedWorkout);
            $entityManager->flush();
        }

        $this->deleteTestUser($owner);
    }

    private function deleteTestUser(User $owner): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $reloaded = $entityManager->getRepository(User::class)->find($owner->id);

        if ($reloaded instanceof User) {
            $entityManager->remove($reloaded);
            $entityManager->flush();
        }
    }

    private function actionUrl(KernelBrowser $client, Workout $workout, string $action): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(WorkoutCrudController::class)
            ->setAction($action)
            ->setEntityId($workout->id)
            ->generateUrl()
        ;
    }
}
