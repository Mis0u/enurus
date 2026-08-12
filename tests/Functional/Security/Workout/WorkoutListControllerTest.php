<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class WorkoutListControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string URL = '/fr/mes-seances';

    // Utilisateur avec 11 séances — pour tester la pagination (page 2 existe)
    private const string USER_11 = 'user-fixture-11-workout@test.com';

    // Utilisateur avec 26 séances — pour tester limit=25
    private const string USER_26 = 'user-fixture-26-workout@test.com';

    // Utilisateur avec 51 séances — pour tester limit=50
    private const string USER_51 = 'user-fixture-51-workout@test.com';

    // Utilisateur sans séance — pour tester l'empty state et l'isolation des données
    private const string USER_EMPTY = 'user-fixture-0@test.com';

    public function testIsAccessibleWhenLogged(): void
    {
        $this->assertPageIsAccessibleWhenLogged(self::USER_11, self::URL, 'Mes séances | Enurus');
    }

    public function testIsRedirectToLoginIfNotLogged(): void
    {
        $this->assertPageIsRedirectToLoginWhenNotLogged(self::URL);
    }

    public function testUserCannotSeeOtherUsersWorkouts(): void
    {
        $client = $this->login(self::USER_EMPTY);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            0,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testEmptyStateIsDisplayedWhenNoWorkouts(): void
    {
        $client = $this->login(self::USER_EMPTY);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            0,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testNoFilterShowsAllWorkouts(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            10,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testFilterWeekReturnsSuccessfulResponse(): void
    {
        $client = $this->login(self::USER_11);
        $client->request(Request::METHOD_GET, self::URL . '?filter=week');

        $this->assertResponseIsSuccessful();
    }

    public function testFilterMonthReturnsSuccessfulResponse(): void
    {
        $client = $this->login(self::USER_11);
        $client->request(Request::METHOD_GET, self::URL . '?filter=month');

        $this->assertResponseIsSuccessful();
    }

    public function testFilterDateShowsOnlyExactDayWorkouts(): void
    {
        // Date sans aucune séance fixture → résultat vide
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?date=2000-01-01');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            0,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testInvalidDateFilterIsIgnoredAndShowsAllWorkouts(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?date=not-a-date');

        $this->assertResponseIsSuccessful();
        // Date invalide ignorée → toutes les séances affichées (10 en page 1)
        $this->assertCount(
            10,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testUnknownFilterIsIgnoredAndShowsAllWorkouts(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?filter=unknown');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            10,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testDefaultLimitIsTen(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            10,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testSecondPageShowsRemainingWorkouts(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?page=2');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            1,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testLimitTwentyFiveShowsTwentyFiveResults(): void
    {
        $client = $this->login(self::USER_26);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?limit=25');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            25,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testLimitFiftyShowsFiftyResults(): void
    {
        $client = $this->login(self::USER_51);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?limit=50');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            50,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testInvalidLimitFallsBackToTen(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?limit=99999');

        $this->assertResponseIsSuccessful();
        $this->assertCount(
            10,
            $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]')
        );
    }

    public function testOutOfRangePageReturnsSuccessfulResponse(): void
    {
        $client = $this->login(self::USER_11);
        $client->request(Request::METHOD_GET, self::URL . '?page=999');

        $this->assertResponseIsSuccessful();
    }

    public function testTonnageIsDisplayed(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        // L'icône éclair (tonnage) est présente au moins une fois dans les métriques
        $this->assertGreaterThan(
            0,
            $crawler->filter('svg path[d="M13 10V3L4 14h7v7l9-11h-7z"]')->count()
        );
    }

    public function testMuscleTagsAreDisplayed(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        // Au moins un tag musculaire primaire (fond rose) est affiché
        $this->assertGreaterThan(
            0,
            $crawler->filter('[class*="rgba(244,63,94,0.15)"]')->count()
        );
    }

    public function testFreeSessionLabelIsDisplayed(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(0, $crawler->filter('.italic')->count());
    }

    public function testDurationIsDisplayedWhenNotNull(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertGreaterThan(
            0,
            $crawler->filter('circle[cx="12"][cy="12"][r="10"]')->count()
        );
    }

    public function testTonnageIsDisplayedInLbsForLbsUser(): void
    {
        $client = $this->login(self::USER_51);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'lbs',
            $crawler->filter('.session-tonnage')->text(),
            'Le tonnage doit être affiché en lbs pour un user lbs'
        );
    }

    public function testTonnageIsDisplayedInKgForKgUser(): void
    {
        $client = $this->login(self::USER_11);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString(
            'kg',
            $crawler->filter('.session-tonnage')->text(),
            'Le tonnage doit être affiché en kg pour un user kg'
        );
    }

    // -------------------------------------------------------------------------
    // Filtre routine
    // -------------------------------------------------------------------------

    public function testRoutineFilterIsHiddenWhenUserHasNoRoutines(): void
    {
        $client = $this->login(self::USER_EMPTY);
        $crawler = $client->request(Request::METHOD_GET, self::URL);

        $this->assertResponseIsSuccessful();
        $this->assertCount(0, $crawler->filter('[data-action="change->workout--list--workout-filters#onRoutineChange"]'));
    }

    public function testRoutineFilterNarrowsToWorkoutsOfThatRoutine(): void
    {
        $client = $this->login(self::USER_EMPTY);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::USER_EMPTY);
        $routine = $this->createRoutine($em, $owner, 'Push Day');
        $this->createWorkout($em, $owner, $routine);
        $freeWorkout = $this->createWorkout($em, $owner, null);

        $crawler = $client->request(Request::METHOD_GET, self::URL . '?routine=' . $routine->id);

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]'));

        $this->removeWorkout($em, $freeWorkout);
    }

    public function testFreeRoutineFilterShowsOnlyFreeSessions(): void
    {
        $client = $this->login(self::USER_EMPTY);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::USER_EMPTY);
        $routine = $this->createRoutine($em, $owner, 'Pull Day');
        $this->createWorkout($em, $owner, $routine);
        $this->createWorkout($em, $owner, null);

        $crawler = $client->request(Request::METHOD_GET, self::URL . '?routine=free');

        $this->assertResponseIsSuccessful();
        $this->assertCount(1, $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]'));
    }

    public function testUnknownRoutineFilterIsIgnoredAndShowsAllWorkouts(): void
    {
        $client = $this->login(self::USER_EMPTY);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::USER_EMPTY);
        // Routine appartenant à un autre utilisateur : ne doit jamais filtrer les séances de USER_EMPTY.
        $otherOwner = $this->getUserByEmail(self::USER_11);
        $otherRoutine = $this->createRoutine($em, $otherOwner, 'Not mine');
        $this->createWorkout($em, $owner, null);
        $this->createWorkout($em, $owner, null);

        $crawler = $client->request(Request::METHOD_GET, self::URL . '?routine=' . $otherRoutine->id);

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]'));

        $em->remove($otherRoutine);
        $em->flush();
    }

    public function testRoutineFilterSelectorIsPreselected(): void
    {
        $client = $this->login(self::USER_EMPTY);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::USER_EMPTY);
        $routine = $this->createRoutine($em, $owner, 'Leg Day');
        $this->createWorkout($em, $owner, $routine);

        $crawler = $client->request(Request::METHOD_GET, self::URL . '?routine=' . $routine->id);

        $this->assertResponseIsSuccessful();
        $selected = $crawler->filter('[data-action="change->workout--list--workout-filters#onRoutineChange"] option[selected]');
        $this->assertSame((string) $routine->id, $selected->attr('value'));
    }

    public function testRoutineFilterIsIgnoredWhenCombinedWithDateFilter(): void
    {
        $client = $this->login(self::USER_EMPTY);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->getUserByEmail(self::USER_EMPTY);
        $routine = $this->createRoutine($em, $owner, 'Push Day');
        $this->createWorkout($em, $owner, $routine);
        $freeWorkout = $this->createWorkout($em, $owner, null);

        // Filtre date exclusif : la routine est ignorée, les deux séances de la veille restent affichées.
        $date = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');
        $crawler = $client->request(Request::METHOD_GET, self::URL . '?date=' . $date . '&routine=' . $routine->id);

        $this->assertResponseIsSuccessful();
        $this->assertCount(2, $crawler->filter('[data-action="click->workout--list--delete-modal#deleteWorkout"]'));

        $this->removeWorkout($em, $freeWorkout);
    }

    private function createRoutine(EntityManagerInterface $em, User $owner, string $name): Routine
    {
        $routine = new Routine();
        $routine->owner = $owner;
        $routine->name = $name . ' ' . uniqid();

        $em->persist($routine);
        $em->flush();

        return $routine;
    }

    private function createWorkout(EntityManagerInterface $em, User $owner, ?Routine $routine): Workout
    {
        $workout = new Workout();
        $workout->owner = $owner;
        $workout->performedAt = new \DateTimeImmutable('-1 day');
        $workout->routine = $routine;

        $em->persist($workout);
        $em->flush();

        return $workout;
    }

    private function removeWorkout(EntityManagerInterface $em, Workout $workout): void
    {
        $em->remove($workout);
        $em->flush();
    }
}
