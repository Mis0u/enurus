<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\ExerciseSet;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseSet>
 */
class ExerciseSetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseSet::class);
    }

    /**
     * @param array<Exercise> $exercises
     * @return array<string, float>
     */
    public function findMaxWeightPerExercise(User $user, array $exercises): array
    {
        if (empty($exercises)) {
            return [];
        }

        /** @var array<int, array{exerciseId: mixed, maxWeight: mixed}> $results */
        $results = $this->createQueryBuilder('es')
            ->select('e.id AS exerciseId, MAX(es.weight) AS maxWeight')
            ->join('es.workoutExercise', 'we')
            ->join('we.exercise', 'e')
            ->join('we.workout', 'w')
            ->where('w.owner = :user')
            ->andWhere('e IN (:exercises)')
            ->setParameter('user', $user)
            ->setParameter('exercises', $exercises)
            ->groupBy('e.id')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            /** @var string|\Stringable $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric|null $maxWeight */
            $maxWeight = $row['maxWeight'];
            $map[(string) $exerciseId] = null !== $maxWeight ? (float) $maxWeight : 0.0;
        }

        return $map;
    }

    /**
     * Tous les sets de l'historique complet de l'utilisateur, triés chronologiquement (séance,
     * puis position d'exercice, puis position de set) — sert de base à la détection progressive
     * des records personnels (un seul appel, le regroupement/la détection se fait ensuite en
     * PHP, même philosophie que DashboardTonnageService::findTonnageSeriesByUser()).
     *
     * @return array<int, array{workoutId: string, exerciseId: string, weight: float, performedAt: DateTimeImmutable}>
     */
    public function findAllSetsChronologicallyByUser(User $user): array
    {
        /** @var array<int, array{workoutId: mixed, exerciseId: mixed, weight: mixed, performedAt: mixed}> $rows */
        $rows = $this->createQueryBuilder('es')
            ->select('w.id as workoutId', 'e.id as exerciseId', 'es.weight as weight', 'w.performedAt as performedAt')
            ->join('es.workoutExercise', 'we')
            ->join('we.exercise', 'e')
            ->join('we.workout', 'w')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'ASC')
            ->addOrderBy('we.position', 'ASC')
            ->addOrderBy('es.position', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \Stringable|string $workoutId */
            $workoutId = $row['workoutId'];
            /** @var \Stringable|string $exerciseId */
            $exerciseId = $row['exerciseId'];
            /** @var numeric $weight */
            $weight = $row['weight'];
            /** @var DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            $result[] = [
                'workoutId' => (string) $workoutId,
                'exerciseId' => (string) $exerciseId,
                'weight' => (float) $weight,
                'performedAt' => $performedAt,
            ];
        }

        return $result;
    }
}
