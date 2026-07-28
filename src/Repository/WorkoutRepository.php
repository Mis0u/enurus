<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workout;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Workout>
 */
class WorkoutRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Workout::class);
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('count(w.id)')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    /**
     * Séances loggées depuis `$since`, tous utilisateurs confondus — signal d'activité pour le
     * dashboard admin (inscriptions ≠ usage réel). `$excludedOwnerIds` exclut les comptes admin,
     * même mécanisme que `UserRepository::excludingAdminsQueryBuilder()` mais appliqué ici via
     * l'association `owner` plutôt que dupliqué en DQL.
     *
     * @param list<Uuid> $excludedOwnerIds
     */
    public function countPerformedSince(\DateTimeImmutable $since, array $excludedOwnerIds = []): int
    {
        $qb = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.performedAt >= :since')
            ->setParameter('since', $since);

        if ([] !== $excludedOwnerIds) {
            $qb->andWhere('w.owner NOT IN (:excludedOwnerIds)')->setParameter('excludedOwnerIds', $excludedOwnerIds);
        }

        /** @var int */
        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Charge le workout le plus récent avec ses workoutExercises, exercises, muscles et sets
     * pré-jointés. Trois requêtes en multi-step hydration (TODO #25) : la première isole l'ID
     * (setMaxResults safe sans JOIN collection, règle CLAUDE.md), la deuxième charge
     * workoutExercises→exercise→exerciseMuscles→muscleGroup (chaîne, une seule collection au-delà
     * de workoutExercises), la troisième charge exerciseSets séparément — `exerciseMuscles` et
     * `exerciseSets` sont deux collections sœurs sous le même workoutExercise, les joindre
     * ensemble produirait un produit cartésien O(n³) (détecté par Doctrine Doctor). Doctrine
     * fusionne automatiquement le résultat de la 3e requête sur les entités déjà managées par
     * identity map, sans requête supplémentaire ni duplication d'objets.
     */
    public function findLatestByUser(User $user): ?Workout
    {
        // Step 1 : ID uniquement — pas de JOIN sur une collection, setMaxResults safe
        $row = $this->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult(AbstractQuery::HYDRATE_ARRAY);

        if (! is_array($row) || ! isset($row['id'])) {
            return null;
        }

        // Step 2 : workout + workoutExercises + exercise + exerciseMuscles + muscleGroup
        /** @var Workout|null $workout */
        $workout = $this->createQueryBuilder('w')
            ->leftJoin('w.workoutExercises', 'we')
            ->addSelect('we')
            ->leftJoin('we.exercise', 'e')
            ->addSelect('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->addSelect('mg')
            ->andWhere('w.id = :id')
            ->setParameter('id', $row['id'])
            ->orderBy('we.position', 'ASC')
            ->getQuery()
            ->getOneOrNullResult();

        if (null === $workout) {
            return null;
        }

        // Step 3 : exerciseSets, fusionné sur les WorkoutExercise déjà managés de l'étape 2
        $this->createQueryBuilder('w')
            ->leftJoin('w.workoutExercises', 'we')
            ->addSelect('we')
            ->leftJoin('we.exerciseSets', 'es')
            ->addSelect('es')
            ->andWhere('w.id = :id')
            ->setParameter('id', $row['id'])
            ->getQuery()
            ->getResult();

        return $workout;
    }

    /**
     * @param array{type?: string, value?: DateTimeImmutable} $filters
     */
    public function findByUserPaginated(User $user, array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('w')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'DESC');

        [$start, $end] = $this->resolveDateRange($filters);

        if (null !== $start && null !== $end) {
            $qb->andWhere('w.performedAt BETWEEN :start AND :end')
                ->setParameter('start', $start)
                ->setParameter('end', $end);
        }

        return $qb;
    }

    /**
     * @param array{type?: string, value?: DateTimeImmutable} $filters
     * @return array{DateTimeImmutable|null, DateTimeImmutable|null}
     */
    private function resolveDateRange(array $filters): array
    {
        $type = $filters['type'] ?? null;
        $now = new DateTimeImmutable();

        return match ($type) {
            'week' => [
                $now->modify('monday this week')->setTime(0, 0, 0),
                $now->modify('sunday this week')->setTime(23, 59, 59),
            ],
            'month' => [
                $now->modify('first day of this month')->setTime(0, 0, 0),
                $now->modify('last day of this month')->setTime(23, 59, 59),
            ],
            'date' => isset($filters['value']) ? [
                DateTimeImmutable::createFromInterface($filters['value'])->setTime(0, 0, 0),
                DateTimeImmutable::createFromInterface($filters['value'])->setTime(23, 59, 59),
            ] : [null, null],
            default => [null, null],
        };
    }
}
