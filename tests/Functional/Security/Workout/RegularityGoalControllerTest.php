<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\DeloadPeriod;
use App\Entity\RegularityGoal;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Repository\RegularityGoalRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class RegularityGoalControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string OTHER_USER = 'user-fixture-11-workout@test.com';

    private const string CALENDAR_URL = '/fr/mes-seances?view=calendar';

    public function testTheCalendarOffersToCreateAGoalWhenThereIsNone(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        self::assertSelectorTextContains('#regularity-goal-title', 'Objectif de régularité');
        self::assertSelectorExists('form[name="regularity_goal"]');
        self::assertAnySelectorTextContains('button', 'Nouvel objectif');
    }

    public function testCreatingAGoalShowsItInTheCalendarAndHidesTheCreateButton(): void
    {
        $client = $this->login(self::USER);

        $this->submitGoal($client, sessions: '3', period: 'week', duration: '4', startDate: 'today');

        self::assertResponseRedirects(self::CALENDAR_URL);
        $client->followRedirect();
        self::assertSelectorTextContains('[aria-labelledby="regularity-goal-title"]', '3 séances par semaine pendant 4 semaines');
        self::assertAnySelectorTextNotContains('button', 'Nouvel objectif');
        self::assertCount(1, $this->goalsOf(self::USER));
    }

    public function testASecondGoalIsRefusedWhileOneIsOngoing(): void
    {
        $client = $this->login(self::USER);
        $this->createGoal(self::USER);

        $this->submitGoal($client, sessions: '1', period: 'day', duration: '2', startDate: 'today');

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Tu as déjà un objectif en cours');
        self::assertCount(1, $this->goalsOf(self::USER));
    }

    public function testAGoalCannotStartInThePast(): void
    {
        $client = $this->login(self::USER);

        $this->submitGoal($client, sessions: '1', period: 'day', duration: '1', startDate: 'yesterday');

        self::assertCount(0, $this->goalsOf(self::USER));
    }

    public function testMoreThanThreeSessionsPerDayAreRejected(): void
    {
        $client = $this->login(self::USER);

        $this->submitGoal($client, sessions: '4', period: 'day', duration: '1', startDate: 'today');

        self::assertCount(0, $this->goalsOf(self::USER));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Pas plus de 3 séances pour ce rythme.');
    }

    /**
     * Le calendrier ne propose que les dates à partir d'aujourd'hui : on peut démarrer demain.
     */
    public function testTheStartDatePickerOnlyOffersTodayAndLater(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        self::assertSelectorExists('#regularity_goal_startDate[data-workout--date-picker-future-only-value="true"]');
    }

    public function testAGoalCanStartTomorrow(): void
    {
        $client = $this->login(self::USER);

        $this->submitGoal($client, sessions: '1', period: 'day', duration: '1', startDate: 'tomorrow');

        self::assertCount(1, $this->goalsOf(self::USER));
    }

    /**
     * Un objectif reste un objectif : il ne se cumule jamais avec un repos programmé.
     */
    public function testAGoalCannotOverlapAPlannedDeload(): void
    {
        $client = $this->login(self::USER);
        $this->createDeload(self::USER, start: '+10 days', end: '+16 days');

        $this->submitGoal($client, sessions: '3', period: 'week', duration: '4', startDate: 'today');

        self::assertCount(0, $this->goalsOf(self::USER));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cet objectif chevauche une semaine de repos programmée.');
    }

    public function testAGoalRightAfterADeloadIsAccepted(): void
    {
        $client = $this->login(self::USER);
        $this->createDeload(self::USER, start: '-7 days', end: 'yesterday');

        $this->submitGoal($client, sessions: '3', period: 'week', duration: '1', startDate: 'today');

        self::assertCount(1, $this->goalsOf(self::USER));
    }

    public function testADeloadCannotBePlannedDuringAGoal(): void
    {
        $client = $this->login(self::USER);
        $this->createGoal(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        $form = $crawler->filter('form[name="deload_period"]')->form();
        $form->setValues([
            'deload_period[startDate]' => new \DateTimeImmutable('today')->format('Y-m-d'),
            'deload_period[endDate]' => new \DateTimeImmutable('today')->format('Y-m-d'),
        ]);
        $client->submit($form);

        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Cette période chevauche ton objectif de régularité');
        self::assertCount(0, $this->getUserByEmail(self::USER)->deloadPeriods);
    }

    public function testAbandoningRemovesTheGoal(): void
    {
        $client = $this->login(self::USER);
        $goal = $this->createGoal(self::USER);

        $this->deleteRequest($client, $this->abandonUrl($goal), $this->abandonCsrfToken($client, $goal));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->goalsOf(self::USER));
    }

    public function testAbandonRequiresAnXmlHttpRequest(): void
    {
        $client = $this->login(self::USER);
        $goal = $this->createGoal(self::USER);

        $client->request(Request::METHOD_DELETE, $this->abandonUrl($goal));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    /**
     * Token généré en tant que propriétaire, puis session basculée : isole le refus du Voter.
     */
    public function testCannotAbandonAnotherUsersGoal(): void
    {
        $client = $this->login(self::OTHER_USER);
        $goal = $this->createGoal(self::OTHER_USER);
        $token = $this->abandonCsrfToken($client, $goal);

        $client->loginUser($this->getUserByEmail(self::USER));
        $this->deleteRequest($client, $this->abandonUrl($goal), $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        self::assertCount(1, $this->goalsOf(self::OTHER_USER));
    }

    /**
     * Comme les Objectifs : le widget n'apparaît qu'à partir du premier objectif créé.
     */
    public function testTheDashboardWidgetAppearsOnceAGoalExists(): void
    {
        $client = $this->login(self::OTHER_USER);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');
        self::assertSelectorNotExists('[data-dashboard-widget="regularity_goal"]');

        $this->createGoal(self::OTHER_USER);
        $client->request(Request::METHOD_GET, '/fr/tableau-de-bord');

        self::assertSelectorTextContains('[data-dashboard-widget="regularity_goal"]', '3 séances par semaine pendant 4 semaines');
    }

    private function submitGoal(KernelBrowser $client, string $sessions, string $period, string $duration, string $startDate): void
    {
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);
        $form = $crawler->filter('form[name="regularity_goal"]')->form();
        $form->setValues([
            'regularity_goal[sessionsPerPeriod]' => $sessions,
            'regularity_goal[period]' => $period,
            'regularity_goal[duration]' => $duration,
            'regularity_goal[startDate]' => new \DateTimeImmutable($startDate)->format('Y-m-d'),
        ]);

        $client->submit($form);
    }

    private function createGoal(string $ownerEmail): RegularityGoal
    {
        $goal = new RegularityGoal();
        $goal->owner = $this->getUserByEmail($ownerEmail);
        $goal->sessionsPerPeriod = 3;
        $goal->period = RegularityGoalPeriodEnum::WEEK;
        $goal->duration = RegularityGoalDurationEnum::FOUR_WEEKS;
        $goal->startDate = new \DateTimeImmutable('today');

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($goal);
        $em->flush();

        return $goal;
    }

    private function createDeload(string $ownerEmail, string $start, string $end): void
    {
        $deload = new DeloadPeriod();
        $deload->owner = $this->getUserByEmail($ownerEmail);
        $deload->startDate = new \DateTimeImmutable($start);
        $deload->endDate = new \DateTimeImmutable($end);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($deload);
        $em->flush();
    }

    /**
     * @return list<RegularityGoal>
     */
    private function goalsOf(string $ownerEmail): array
    {
        /** @var RegularityGoalRepository $repository */
        $repository = static::getContainer()->get(RegularityGoalRepository::class);

        return $repository->findByOwnerNewestFirst($this->getUserByEmail($ownerEmail));
    }

    private function abandonUrl(RegularityGoal $goal): string
    {
        return \sprintf('/fr/mes-seances/objectif-regularite/%s/abandonner', $goal->id);
    }

    private function abandonCsrfToken(KernelBrowser $client, RegularityGoal $goal): string
    {
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        return $crawler->filter(\sprintf('button[data-delete-url*="%s"]', $goal->id))->attr('data-token')
            ?? throw new \LogicException('Abandon CSRF token not found on the calendar view.');
    }
}
