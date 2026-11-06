<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Routine;

use App\DataFixtures\UserFixtures;
use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Repository\RoutineRepository;
use App\Repository\UserRepository;
use App\Repository\WorkoutRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class RoutineArchivingTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string OWNER = UserFixtures::USER_ROUTINE_OWNER;

    private const string OTHER_USER = UserFixtures::USER_ROUTINE_OTHER;

    private const string LIST_URL = '/fr/mes-routines';

    private const string ARCHIVED_LIST_URL = '/fr/mes-routines?view=archived';

    private const string ARCHIVED_TAB_SELECTOR = 'a[href$="view=archived"]';

    // -------------------------------------------------------------------------
    // SUPPRESSION D'UNE ROUTINE UTILISÉE
    // -------------------------------------------------------------------------

    public function testRoutineUsedByAWorkoutIsArchivedInsteadOfDeleted(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $workoutId = $this->createWorkoutUsing($routine)->id;
        $routineId = $routine->id;

        $this->sendDeleteRequest($client, $routine);

        self::assertResponseIsSuccessful();
        $content = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertTrue($content['archived']);

        $this->entityManager()->clear();

        $reloadedRoutine = $this->routineRepository()->find($routineId);
        self::assertNotNull($reloadedRoutine);
        self::assertTrue($reloadedRoutine->archived);

        /** @var WorkoutRepository $workoutRepository */
        $workoutRepository = static::getContainer()->get(WorkoutRepository::class);
        $reloadedWorkout = $workoutRepository->find($workoutId);
        self::assertNotNull($reloadedWorkout);
        self::assertNotNull($reloadedWorkout->routine);
        self::assertSame((string) $routineId, (string) $reloadedWorkout->routine->id);
    }

    public function testUnusedRoutineDeletionIsNotReportedAsArchived(): void
    {
        $client = $this->login(self::OWNER);

        $this->sendDeleteRequest($client, $this->getOwnerRoutine());

        self::assertResponseIsSuccessful();
        $content = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertFalse($content['archived']);
    }

    // -------------------------------------------------------------------------
    // POP-UP DE CONFIRMATION
    // -------------------------------------------------------------------------

    public function testPopupAnnouncesArchivingForAUsedRoutine(): void
    {
        $client = $this->login(self::OWNER);
        $this->createWorkoutUsing($this->getOwnerRoutine());

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        $popup = $crawler->filter('[data-controller="routine--delete"]');
        self::assertSame('Archiver la routine', $popup->attr('data-routine--delete-confirm-title-value'));
        self::assertSame('Archiver', $popup->attr('data-routine--delete-confirm-button-value'));
        self::assertStringContainsString('1 séance', (string) $popup->attr('data-routine--delete-confirm-text-value'));
    }

    public function testPopupAnnouncesDeletionForAnUnusedRoutine(): void
    {
        $client = $this->login(self::OWNER);

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        $popup = $crawler->filter('[data-controller="routine--delete"]');
        self::assertSame('Supprimer la routine', $popup->attr('data-routine--delete-confirm-title-value'));
        self::assertStringNotContainsString('séances libres', (string) $popup->attr('data-routine--delete-confirm-text-value'));
    }

    // -------------------------------------------------------------------------
    // ONGLET « ARCHIVÉES »
    // -------------------------------------------------------------------------

    public function testArchivedTabIsAbsentWithoutArchivedRoutine(): void
    {
        $client = $this->login(self::OWNER);

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertCount(0, $crawler->filter(self::ARCHIVED_TAB_SELECTOR));
    }

    public function testArchivedRoutineLeavesTheActiveListAndShowsTheTab(): void
    {
        $client = $this->login(self::OWNER);
        $this->archive($this->getOwnerRoutine());

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertCount(0, $crawler->filter('.routine-card'));
        self::assertStringContainsString('Archivées (1)', $crawler->filter(self::ARCHIVED_TAB_SELECTOR)->text());
    }

    public function testArchivedTabListsArchivedRoutinesWithARestoreButton(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->createWorkoutUsing($routine);
        $this->archive($routine);

        $crawler = $client->request(Request::METHOD_GET, self::ARCHIVED_LIST_URL);

        self::assertCount(1, $crawler->filter('.routine-card'));
        self::assertCount(1, $crawler->filter('[data-controller="routine--restore"]'));
        self::assertCount(0, $crawler->filter('[data-controller="routine--delete"]'));
        self::assertStringContainsString('1 séance', $crawler->filter('.routine-card')->text());
    }

    public function testArchivedViewFallsBackToActiveRoutinesWhenNothingIsArchived(): void
    {
        $client = $this->login(self::OWNER);

        $crawler = $client->request(Request::METHOD_GET, self::ARCHIVED_LIST_URL);

        self::assertCount(1, $crawler->filter('[data-controller="routine--delete"]'));
    }

    public function testOtherUserArchivedRoutinesAreNotListed(): void
    {
        $client = $this->login(self::OTHER_USER);
        $this->archive($this->getOwnerRoutine());

        $crawler = $client->request(Request::METHOD_GET, self::LIST_URL);

        self::assertCount(0, $crawler->filter(self::ARCHIVED_TAB_SELECTOR));
    }

    // -------------------------------------------------------------------------
    // PAGES INACCESSIBLES UNE FOIS ARCHIVÉE
    // -------------------------------------------------------------------------

    public function testArchivedRoutineDetailPageIsNotFound(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(Request::METHOD_GET, self::LIST_URL . '/' . $this->idOf($routine));

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testArchivedRoutineEditPageIsNotFound(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(Request::METHOD_GET, self::LIST_URL . '/' . $this->idOf($routine) . '/modifier');

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    public function testArchivedRoutineCannotFillANewWorkout(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(
            Request::METHOD_GET,
            '/fr/enregistre-seance/bloc-exercices-routine?routineId=' . $this->idOf($routine),
        );

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
    }

    // -------------------------------------------------------------------------
    // RÉUTILISATION DU NOM
    // -------------------------------------------------------------------------

    public function testNameOfAnArchivedRoutineIsAvailable(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(Request::METHOD_GET, '/fr/mes-routines/verifier-nom?name=' . rawurlencode($routine->name));

        $content = json_decode((string) $client->getResponse()->getContent(), true);
        self::assertIsArray($content);
        self::assertTrue($content['available']);
    }

    public function testNewRoutineCanReuseTheNameOfAnArchivedRoutine(): void
    {
        $this->login(self::OWNER);
        $archived = $this->getOwnerRoutine();
        $this->archive($archived);

        $routine = new Routine();
        $routine->owner = $archived->owner;
        $routine->name = $archived->name;

        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);

        self::assertCount(0, $validator->validateProperty($routine, 'name'));
        self::assertCount(0, $validator->validate($routine));
    }

    public function testNewRoutineCannotReuseTheNameOfAnActiveRoutine(): void
    {
        $this->login(self::OWNER);
        $active = $this->getOwnerRoutine();

        $routine = new Routine();
        $routine->owner = $active->owner;
        $routine->name = $active->name;

        /** @var ValidatorInterface $validator */
        $validator = static::getContainer()->get(ValidatorInterface::class);

        self::assertCount(1, $validator->validate($routine));
    }

    // -------------------------------------------------------------------------
    // DÉSARCHIVAGE
    // -------------------------------------------------------------------------

    public function testRestoreIsRejectedWithoutXhrHeader(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(Request::METHOD_POST, $this->restoreUrl($routine));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testRestoreRedirectsAnonymousVisitorToLogin(): void
    {
        $client = static::createClient();

        $client->request(Request::METHOD_POST, $this->restoreUrl($this->getOwnerRoutine()), server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertResponseRedirects();
    }

    public function testRestoreIsForbiddenWithInvalidCsrfToken(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(Request::METHOD_POST, $this->restoreUrl($routine), server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_X-CSRF-Token' => 'invalid-token',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRestoreIsForbiddenForOtherUser(): void
    {
        $client = $this->login(self::OTHER_USER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $client->request(Request::METHOD_POST, $this->restoreUrl($routine), server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testRestoredRoutineIsBackInTheActiveList(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);
        $routineId = $routine->id;

        $this->sendRestoreRequest($client, $routine);

        self::assertResponseIsSuccessful();
        $this->entityManager()->clear();

        $reloaded = $this->routineRepository()->find($routineId);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->archived);
    }

    public function testRestoreIsRefusedWhenAnActiveRoutineHasTheSameName(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);
        $this->createActiveRoutineNamed($routine->owner, mb_strtoupper($routine->name));
        $routineId = $routine->id;

        $this->sendRestoreRequest($client, $routine);

        self::assertResponseStatusCodeSame(Response::HTTP_CONFLICT);
        $this->entityManager()->clear();

        $reloaded = $this->routineRepository()->find($routineId);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->archived);
    }

    // -------------------------------------------------------------------------
    // FILTRE DE « MES SÉANCES » ET CRÉATION DE SÉANCE
    // -------------------------------------------------------------------------

    public function testWorkoutFilterHasNoArchivedGroupWithoutArchivedRoutine(): void
    {
        $client = $this->login(self::OWNER);

        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-seances');

        self::assertCount(0, $crawler->filter('select optgroup'));
    }

    public function testWorkoutFilterGroupsArchivedRoutines(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->createWorkoutUsing($routine);
        $this->archive($routine);

        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-seances');

        $group = $crawler->filter('select optgroup[label="Archivées"]');
        self::assertCount(1, $group);
        self::assertSame($routine->name, $group->filter('option')->text());
    }

    public function testWorkoutListCanBeFilteredOnAnArchivedRoutine(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->createWorkoutUsing($routine);
        $this->archive($routine);

        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-seances?routine=' . $this->idOf($routine));

        self::assertCount(1, $crawler->filter('select optgroup option[selected]'));
    }

    public function testArchivedRoutineIsNotOfferedForANewWorkout(): void
    {
        $client = $this->login(self::OWNER);
        $routine = $this->getOwnerRoutine();
        $this->archive($routine);

        $crawler = $client->request(Request::METHOD_GET, '/fr/enregistre-seance');

        self::assertCount(0, $crawler->filter(\sprintf('select option[value="%s"]', $this->idOf($routine))));
    }

    // -------------------------------------------------------------------------
    // HELPERS PRIVÉS
    // -------------------------------------------------------------------------

    private function sendDeleteRequest(KernelBrowser $client, Routine $routine): void
    {
        $id = $this->idOf($routine);
        $token = $this->csrfTokenFromPage(
            $client,
            self::LIST_URL,
            \sprintf('div[data-routine--delete-url-value*="%s"]', $id),
            'data-routine--delete-csrf-token-value',
        );

        $client->request(Request::METHOD_DELETE, self::LIST_URL . '/' . $id . '/supprimer', server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_X-CSRF-Token' => $token,
        ]);
    }

    private function sendRestoreRequest(KernelBrowser $client, Routine $routine): void
    {
        $token = $this->csrfTokenFromPage(
            $client,
            self::ARCHIVED_LIST_URL,
            \sprintf('div[data-routine--restore-url-value*="%s"]', $this->idOf($routine)),
            'data-routine--restore-csrf-token-value',
        );

        $client->request(Request::METHOD_POST, $this->restoreUrl($routine), server: [
            'HTTP_X-Requested-With' => 'XMLHttpRequest',
            'HTTP_X-CSRF-Token' => $token,
        ]);
    }

    private function restoreUrl(Routine $routine): string
    {
        return self::LIST_URL . '/' . $this->idOf($routine) . '/desarchiver';
    }

    private function idOf(Routine $routine): string
    {
        self::assertNotNull($routine->id);

        return $routine->id->toRfc4122();
    }

    private function archive(Routine $routine): void
    {
        $routine->archived = true;
        $this->entityManager()->flush();
    }

    private function createWorkoutUsing(Routine $routine): Workout
    {
        $workout = new Workout();
        $workout->owner = $routine->owner;
        $workout->routine = $routine;

        $this->entityManager()->persist($workout);
        $this->entityManager()->flush();

        return $workout;
    }

    private function createActiveRoutineNamed(User $owner, string $name): void
    {
        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = $name;

        $this->entityManager()->persist($routine);
        $this->entityManager()->flush();
    }

    private function getOwnerRoutine(): Routine
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $owner = $userRepository->findOneBy([
            'email' => self::OWNER,
        ]);
        self::assertNotNull($owner);

        $routine = $this->routineRepository()->findOneBy([
            'owner' => $owner,
            'name' => 'Push Day',
        ]);
        self::assertNotNull($routine);

        return $routine;
    }

    private function routineRepository(): RoutineRepository
    {
        /** @var RoutineRepository $repository */
        $repository = static::getContainer()->get(RoutineRepository::class);

        return $repository;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
