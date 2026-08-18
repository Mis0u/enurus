<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Routine;
use App\Entity\User;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;

/**
 * Statistiques d'usage d'une routine (nombre de séances basées dessus, dernière utilisation,
 * durée moyenne) — dérivées de Workout::routine, jamais de RoutineExercise qui ne porte aucune
 * donnée d'exécution.
 */
class RoutineStatsRepository
{
    public function __construct(
        private readonly WorkoutRepository $workoutRepository,
    ) {
    }

    /**
     * Dernière date d'utilisation — calculée en PHP à partir de `findUsageDatesByRoutine()` plutôt
     * qu'un `MAX()` DQL, dont l'hydratation d'un agrégat sur un champ `datetime_immutable` est
     * ambiguë (même règle que `WorkoutMuscleRepository::findLastSolicitationDatesByMuscleGroup`).
     *
     * @param list<\DateTimeImmutable> $usageDates déjà triées ASC par `findUsageDatesByRoutine()`
     */
    public function lastUsedAtFromDates(array $usageDates): ?\DateTimeImmutable
    {
        return [] === $usageDates ? null : end($usageDates);
    }

    /**
     * Moyenne calculée uniquement sur les séances où la durée a été renseignée — une séance sans
     * durée ne doit pas faire baisser artificiellement la moyenne des séances où elle l'a été.
     */
    public function averageDurationByRoutine(Routine $routine, User $user): ?float
    {
        /** @var numeric|null $average */
        $average = $this->routineWorkoutsQueryBuilder($routine, $user)
            ->select('AVG(w.duration)')
            ->andWhere('w.duration IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return null !== $average ? (float) $average : null;
    }

    /**
     * Une ligne par séance basée sur cette routine — sert de base au bucketing (semaine/mois/année)
     * du graphique d'usage, fait en PHP comme le widget Tonnage du dashboard.
     *
     * @return list<\DateTimeImmutable>
     */
    public function findUsageDatesByRoutine(Routine $routine, User $user): array
    {
        /** @var array<int, array{performedAt: mixed}> $rows */
        $rows = $this->routineWorkoutsQueryBuilder($routine, $user)
            ->select('w.performedAt as performedAt')
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        return array_values(array_map(static function (array $row): \DateTimeImmutable {
            /** @var \DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];

            return $performedAt;
        }, $rows));
    }

    private function routineWorkoutsQueryBuilder(Routine $routine, User $user): QueryBuilder
    {
        return $this->workoutRepository->createQueryBuilder('w')
            ->andWhere('w.routine = :routine')
            ->andWhere('w.owner = :user')
            ->setParameter('routine', $routine)
            ->setParameter('user', $user);
    }
}
