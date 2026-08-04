<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

use App\Controller\Admin\RoutineCrudController;
use App\Entity\Exercise;
use App\Entity\Routine;
use App\Entity\RoutineExercise;
use App\Entity\User;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class RoutineCrudControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string ADMIN = 'admin-fixture@test.com';

    public function testIndexListsRoutine(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $routine] = $this->createRoutine('routine-crud-index@test.com', 'Routine index');

        $client->request(Request::METHOD_GET, $this->indexUrl());

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'Routine index');
        self::assertSelectorTextContains('table.datagrid', 'routine-crud-index@test.com');
        self::assertSelectorExists('a[href="' . $this->actionUrl($client, $routine, 'detail') . '"]');

        $this->cleanup($owner, $routine);
    }

    public function testDetailShowsDescriptionAndExercises(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $routine] = $this->createRoutine('routine-crud-detail@test.com', 'Routine détail', 'Une description précise.');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $exercise = new Exercise();
        $exercise->name = 'Développé couché';
        $entityManager->persist($exercise);

        $routineExercise = new RoutineExercise();
        $routineExercise->routine = $routine;
        $routineExercise->exercise = $exercise;
        $routineExercise->position = 0;
        $entityManager->persist($routineExercise);
        $entityManager->flush();
        // `clear()` : le client de test ne rebootant pas systématiquement le kernel entre deux
        // appels, `$routine` resterait sinon le même objet PHP dont `routineExercises` est
        // l'ArrayCollection vide posée par le constructeur, jamais réhydratée depuis la base.
        $entityManager->clear();

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $routine, 'detail'));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Une description précise.');
        self::assertSelectorTextContains('body', 'Développé couché');

        /** @var RoutineExercise $reloadedRoutineExercise */
        $reloadedRoutineExercise = $entityManager->getRepository(RoutineExercise::class)->find($routineExercise->id);
        /** @var Exercise $reloadedExercise */
        $reloadedExercise = $entityManager->getRepository(Exercise::class)->find($exercise->id);
        $entityManager->remove($reloadedRoutineExercise);
        $entityManager->remove($reloadedExercise);
        $entityManager->flush();
        $this->cleanup($owner, $routine);
    }

    public function testEditIsDisabled(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $routine] = $this->createRoutine('routine-crud-edit@test.com', 'Routine edit');

        $client->request(Request::METHOD_GET, $this->actionUrl($client, $routine, 'edit'));

        self::assertResponseStatusCodeSame(403);

        $this->cleanup($owner, $routine);
    }

    public function testDeleteRemovesRoutine(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $routine] = $this->createRoutine('routine-crud-delete@test.com', 'Routine à supprimer');

        $token = $this->csrfTokenFromPage(
            $client,
            $this->actionUrl($client, $routine, 'detail'),
            'input[name="token"]',
            'value',
        );

        $client->request(Request::METHOD_POST, $this->actionUrl($client, $routine, 'delete'), [
            'token' => $token,
        ]);

        self::assertResponseRedirects();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($entityManager->getRepository(Routine::class)->find($routine->id));

        $this->deleteTestUser($owner);
    }

    public function testOwnerFilterNarrowsIndexToMatchingOwner(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $routine] = $this->createRoutine('routine-crud-filter@test.com', 'Routine filtrée');

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
            ->setController(RoutineCrudController::class)
            ->setAction('index')
            ->set('filters[owner][comparison]', '=')
            ->set('filters[owner][value]', (string) $owner->id)
            ->generateUrl()
        ;

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('table.datagrid', 'Routine filtrée');

        $this->cleanup($owner, $routine);
    }

    public function testFiltersRenderWithoutError(): void
    {
        $client = $this->login(self::ADMIN);

        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator->setController(RoutineCrudController::class)->setAction('renderFilters')->generateUrl();

        $client->request(Request::METHOD_GET, $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * Les routes /admin n'ont pas de préfixe _locale (contrairement au reste de l'app) et le
     * serveur tourne en UTC — sans AdminLocaleListener + Crud::setTimezone('Europe/Paris')
     * (cf. RoutineCrudController::configureCrud()), ce champ s'afficherait en anglais et en heure
     * UTC brute plutôt qu'en français à l'heure de Paris.
     */
    public function testIndexDisplaysCreatedAtInFrenchAndParisTimezone(): void
    {
        $client = $this->login(self::ADMIN);
        [$owner, $routine] = $this->createRoutine('routine-crud-timezone@test.com', 'Routine fuseau');

        $expectedParisTime = $routine->createdAt
            ->setTimezone(new \DateTimeZone('Europe/Paris'))
            ->format('H:i');

        $crawler = $client->request(Request::METHOD_GET, $this->indexUrl());
        $body = $crawler->filter('body')->text();

        self::assertResponseIsSuccessful();
        self::assertStringContainsString($expectedParisTime, $body, 'La date affichée doit être convertie en heure de Paris.');
        self::assertStringNotContainsString('AM', $body);
        self::assertStringNotContainsString('PM', $body);

        $this->cleanup($owner, $routine);
    }

    /**
     * @return array{0: User, 1: Routine}
     */
    private function createRoutine(string $email, string $name, ?string $description = null): array
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

        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = $name;
        $routine->description = $description;
        $entityManager->persist($routine);

        $entityManager->flush();

        return [$owner, $routine];
    }

    private function cleanup(User $owner, Routine $routine): void
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $reloadedRoutine = $entityManager->getRepository(Routine::class)->find($routine->id);

        if ($reloadedRoutine instanceof Routine) {
            $entityManager->remove($reloadedRoutine);
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

    private function indexUrl(): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator->setController(RoutineCrudController::class)->setAction('index')->generateUrl();
    }

    private function actionUrl(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, Routine $routine, string $action): string
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = static::getContainer()->get(AdminUrlGenerator::class);

        return $adminUrlGenerator
            ->setController(RoutineCrudController::class)
            ->setAction($action)
            ->setEntityId($routine->id)
            ->generateUrl()
        ;
    }
}
