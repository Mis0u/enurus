<?php

declare(strict_types=1);

namespace App\Tests\Functional\Security\Workout;

use App\Entity\DeloadPeriod;
use App\Entity\User;
use App\Repository\DeloadPeriodRepository;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class DeloadPeriodControllerTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-0@test.com';

    private const string OTHER_USER = 'user-fixture-11-workout@test.com';

    private const string CALENDAR_URL = '/fr/mes-seances?view=calendar';

    public function testCalendarViewIsAccessible(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        self::assertResponseIsSuccessful();
    }

    public function testCalendarViewShowsListTabByDefault(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, '/fr/mes-seances');

        self::assertResponseIsSuccessful();
        // Le calendrier n'est jamais présent quand l'onglet Liste est actif — évite un faux
        // positif si la vue calendrier était rendue par erreur en plus de la liste.
        self::assertCount(0, $crawler->filter('[data-controller="workout--list--modal"]'));
    }

    public function testCreateValidPeriodPersistsAndRedirectsToCalendarView(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        // Dates relatives : un repos ne se programme jamais dans le passé.
        $start = new \DateTimeImmutable('+1 day')->format('Y-m-d');
        $end = new \DateTimeImmutable('+7 days')->format('Y-m-d');

        $form = $crawler->filter('form[name="deload_period"]')->form();
        $form->setValues([
            'deload_period[startDate]' => $start,
            'deload_period[endDate]' => $end,
            'deload_period[note]' => 'Semaine allégée',
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/fr/mes-seances?view=calendar');

        $deloadPeriod = $this->findLatestDeloadPeriod(self::USER);
        self::assertNotNull($deloadPeriod);
        self::assertSame($start, $deloadPeriod->startDate->format('Y-m-d'));
        self::assertSame($end, $deloadPeriod->endDate->format('Y-m-d'));
        self::assertSame('Semaine allégée', $deloadPeriod->note);

        $this->removeDeloadPeriod($deloadPeriod);
    }

    /**
     * Un repos se programme : aujourd'hui ou plus tard, jamais dans le passé.
     */
    public function testAPeriodStartingInThePastIsRejected(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        $form = $crawler->filter('form[name="deload_period"]')->form();
        $form->setValues([
            'deload_period[startDate]' => new \DateTimeImmutable('yesterday')->format('Y-m-d'),
            'deload_period[endDate]' => new \DateTimeImmutable('+5 days')->format('Y-m-d'),
        ]);
        $client->submit($form);

        self::assertNull($this->findLatestDeloadPeriod(self::USER));
        $client->followRedirect();
        self::assertSelectorTextContains('body', 'Une semaine de repos ne peut pas commencer dans le passé.');
    }

    public function testAPeriodStartingTodayIsAccepted(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        $form = $crawler->filter('form[name="deload_period"]')->form();
        $form->setValues([
            'deload_period[startDate]' => new \DateTimeImmutable('today')->format('Y-m-d'),
            'deload_period[endDate]' => new \DateTimeImmutable('+6 days')->format('Y-m-d'),
        ]);
        $client->submit($form);

        self::assertNotNull($this->findLatestDeloadPeriod(self::USER));
    }

    public function testTheDatePickersOnlyOfferTodayAndLater(): void
    {
        $client = $this->login(self::USER);
        $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        self::assertSelectorExists('#deload_period_startDate[data-workout--date-picker-future-only-value="true"]');
        self::assertSelectorExists('#deload_period_endDate[data-workout--date-picker-future-only-value="true"]');
    }

    public function testCreateWithEndDateBeforeStartDateIsRejected(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        $form = $crawler->filter('form[name="deload_period"]')->form();
        $form->setValues([
            'deload_period[startDate]' => new \DateTimeImmutable('+7 days')->format('Y-m-d'),
            'deload_period[endDate]' => new \DateTimeImmutable('+1 day')->format('Y-m-d'),
        ]);

        $client->submit($form);

        self::assertResponseRedirects('/fr/mes-seances?view=calendar');
        self::assertNull($this->findLatestDeloadPeriod(self::USER));
    }

    public function testDeleteWithNonXmlHttpRequestReturns400(): void
    {
        $client = $this->login(self::USER);
        $deloadPeriod = $this->createDeloadPeriod(self::USER);

        $client->request(Request::METHOD_DELETE, $this->deleteUrl($deloadPeriod));

        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $this->removeDeloadPeriod($deloadPeriod);
    }

    public function testDeleteWithInvalidCsrfTokenIsRejected(): void
    {
        $client = $this->login(self::USER);
        $deloadPeriod = $this->createDeloadPeriod(self::USER);

        $this->deleteRequest($client, $this->deleteUrl($deloadPeriod), 'invalid-token');

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->removeDeloadPeriod($deloadPeriod);
    }

    /**
     * Même client tout du long (comme ExerciseGoalDeleteControllerTest) : le token CSRF est
     * généré alors que le client est authentifié comme le propriétaire réel de la période, puis
     * la session est basculée vers un autre utilisateur avant l'appel — isole précisément le
     * refus par le Voter (403 métier), pas un simple échec CSRF (qui donnerait aussi 403 mais
     * pour une raison différente et non testée ici).
     */
    public function testCannotDeleteAnotherUsersPeriod(): void
    {
        $client = $this->login(self::OTHER_USER);
        $deloadPeriod = $this->createDeloadPeriod(self::OTHER_USER);
        $token = $this->deleteCsrfToken($client, $deloadPeriod);

        $client->loginUser($this->getUserByEmail(self::USER));

        $this->deleteRequest($client, $this->deleteUrl($deloadPeriod), $token);

        self::assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);

        $this->removeDeloadPeriod($deloadPeriod);
    }

    public function testDeleteReturnsSuccessAndRemovesThePeriod(): void
    {
        $client = $this->login(self::USER);
        $deloadPeriod = $this->createDeloadPeriod(self::USER);
        $token = $this->deleteCsrfToken($client, $deloadPeriod);
        $deloadPeriodId = $deloadPeriod->id;

        $this->deleteRequest($client, $this->deleteUrl($deloadPeriod), $token);

        self::assertResponseIsSuccessful();

        /** @var DeloadPeriodRepository $repository */
        $repository = static::getContainer()->get(DeloadPeriodRepository::class);
        self::assertNull($repository->find($deloadPeriodId));
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function createDeloadPeriod(string $ownerEmail): DeloadPeriod
    {
        $owner = $this->getUserByEmail($ownerEmail);

        $deloadPeriod = new DeloadPeriod();
        $deloadPeriod->owner = $owner;
        $deloadPeriod->startDate = new \DateTimeImmutable('2026-09-29');
        $deloadPeriod->endDate = new \DateTimeImmutable('2026-10-05');

        $em = $this->entityManager();
        $em->persist($deloadPeriod);
        $em->flush();

        return $deloadPeriod;
    }

    private function findLatestDeloadPeriod(string $ownerEmail): ?DeloadPeriod
    {
        /** @var User $owner */
        $owner = $this->getUserByEmail($ownerEmail);

        /** @var DeloadPeriodRepository $repository */
        $repository = static::getContainer()->get(DeloadPeriodRepository::class);

        /** @var DeloadPeriod|null */
        return $repository->findOneBy(
            [
                'owner' => $owner,
            ],
            [
                'id' => 'DESC',
            ],
        );
    }

    private function removeDeloadPeriod(DeloadPeriod $deloadPeriod): void
    {
        $em = $this->entityManager();
        /** @var DeloadPeriod $managed */
        $managed = $em->getRepository(DeloadPeriod::class)->find($deloadPeriod->id);
        $em->remove($managed);
        $em->flush();
    }

    private function deleteUrl(DeloadPeriod $deloadPeriod): string
    {
        return \sprintf('/fr/mes-seances/deload/%s/supprimer', $deloadPeriod->id);
    }

    private function deleteCsrfToken(KernelBrowser $client, DeloadPeriod $deloadPeriod): string
    {
        $crawler = $client->request(Request::METHOD_GET, self::CALENDAR_URL);

        $value = $crawler->filter(\sprintf('button[data-delete-url*="%s"]', $deloadPeriod->id))->attr('data-token');

        if (null === $value) {
            throw new \LogicException('Delete CSRF token not found on the calendar view.');
        }

        return $value;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface */
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
