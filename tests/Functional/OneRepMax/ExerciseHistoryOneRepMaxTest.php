<?php

declare(strict_types=1);

namespace App\Tests\Functional\OneRepMax;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Enum\Entity\Exercise\MeasurementType;
use App\Service\OneRepMax\OneRepMaxCalculator;
use App\Tests\Functional\Security\Trait\FunctionalTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ExerciseHistoryOneRepMaxTest extends WebTestCase
{
    use FunctionalTestTrait;

    private const string USER = 'user-fixture-11-workout@test.com';

    private const string ONE_REP_MAX = '#exercise-history-one-rep-max';

    public function testTheRecordCardShowsTheEstimatedOneRepMax(): void
    {
        $client = $this->login(self::USER);
        $exercise = $this->loggedExercise(MeasurementType::WEIGHT_REPS, bodyweight: false);

        $client->request(Request::METHOD_GET, $this->historyUrl($exercise));

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains(self::ONE_REP_MAX, '1RM estimé');
        self::assertSelectorTextContains(self::ONE_REP_MAX, '×');
    }

    /**
     * Les comptes des fixtures n'ont pas de poids de corps : l'exercice passé au poids de corps
     * ne peut plus être estimé sur sa charge réelle.
     */
    public function testABodyweightExerciseAsksForTheUserBodyweight(): void
    {
        $client = $this->login(self::USER);
        $exercise = $this->loggedExercise(MeasurementType::WEIGHT_REPS, bodyweight: false);
        $exercise->bodyweightPercent = 92.0;
        $this->entityManager()->flush();

        $crawler = $client->request(Request::METHOD_GET, $this->historyUrl($exercise));

        self::assertSelectorTextContains(self::ONE_REP_MAX, 'poids de corps');
        self::assertCount(1, $crawler->filter(self::ONE_REP_MAX . ' a[href="/fr/reglages#fieldBodyweight"]'));
    }

    public function testAnExerciseWithoutHistoryShowsNoEstimate(): void
    {
        $client = $this->login(self::USER);
        $crawler = $client->request(Request::METHOD_GET, $this->historyUrl($this->exerciseNeverLogged()));

        self::assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter(self::ONE_REP_MAX));
    }

    private function historyUrl(Exercise $exercise): string
    {
        return \sprintf('/fr/bibliotheque/exercice/%s/historique', $exercise->id);
    }

    private function loggedExercise(MeasurementType $measurementType, bool $bodyweight): Exercise
    {
        $exercise = $this->entityManager()->createQueryBuilder()
            ->select('e')
            ->from(ExerciseSet::class, 'es')
            ->join('es.workoutExercise', 'we')
            ->join('we.workout', 'w')
            ->join('w.owner', 'u')
            ->join(Exercise::class, 'e', 'WITH', 'e = we.exercise')
            ->andWhere('u.email = :email')
            ->andWhere('e.measurementType = :measurementType')
            ->andWhere($bodyweight ? 'e.bodyweightPercent IS NOT NULL' : 'e.bodyweightPercent IS NULL')
            ->andWhere('es.reps <= :maxReps')
            ->setParameter('email', self::USER)
            ->setParameter('measurementType', $measurementType)
            ->setParameter('maxReps', OneRepMaxCalculator::MAX_REPS)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (! $exercise instanceof Exercise) {
            throw new \LogicException('The fixture user should have logged such an exercise.');
        }

        return $exercise;
    }

    private function exerciseNeverLogged(): Exercise
    {
        $exercise = $this->entityManager()->createQueryBuilder()
            ->select('e')
            ->from(Exercise::class, 'e')
            ->andWhere('e.isPublic = true')
            ->andWhere('e NOT IN (SELECT IDENTITY(we.exercise) FROM App\Entity\WorkoutExercise we JOIN we.workout w JOIN w.owner u WHERE u.email = :email)')
            ->setParameter('email', self::USER)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (! $exercise instanceof Exercise) {
            throw new \LogicException('The fixture user should have left a public exercise aside.');
        }

        return $exercise;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
