<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\AbstractQuery;

/**
 * Requêtes de tonnage (poids × reps) — extrait de WorkoutRepository (split SRP, voir CLAUDE.md
 * TODO), responsabilité distincte de la pagination/des muscles sollicités/des comptages bruts.
 */
final class WorkoutTonnageRepository
{
    /**
     * Sous-requête DQL de tonnage réutilisée par toutes les requêtes agrégeant le tonnage
     * (poids × reps) d'une séance — évite de la dupliquer dans chaque méthode.
     */
    private const string TONNAGE_SUBQUERY_DQL = '(SELECT COALESCE(SUM(s.weight * s.reps), 0)
              FROM App\Entity\ExerciseSet s
              JOIN s.workoutExercise we2
              WHERE we2.workout = w
            ) as tonnage';

    public function __construct(
        private readonly WorkoutRepository $workoutRepository,
    ) {
    }

    /**
     * @param string[] $workoutIds
     * @return array<string, float>
     */
    public function findTonnageByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        /** @var array<int, array{workoutId: mixed, tonnage: mixed}> $rows */
        $rows = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.id as workoutId')
            ->addSelect(self::TONNAGE_SUBQUERY_DQL)
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutId */
            $workoutId = $row['workoutId'];
            /** @var numeric|null $tonnage */
            $tonnage = $row['tonnage'];

            $result[(string) $workoutId] = null !== $tonnage ? (float) $tonnage : 0.0;
        }

        return $result;
    }

    /**
     * Une ligne par séance (date + tonnage en kg) sur la plage donnée, triée chronologiquement.
     * Sert de base à toutes les granularités du graphique de tonnage (jour, semaine, mois),
     * le regroupement se fait ensuite en PHP.
     *
     * @return array<int, array{performedAt: DateTimeImmutable, tonnage: float}>
     */
    public function findTonnageSeriesByUser(User $user, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        /** @var array<int, array{performedAt: mixed, tonnage: mixed}> $rows */
        $rows = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.performedAt as performedAt')
            ->addSelect(self::TONNAGE_SUBQUERY_DQL)
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('w.performedAt', 'ASC')
            ->getQuery()
            ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $result = [];
        foreach ($rows as $row) {
            /** @var \DateTimeImmutable $performedAt */
            $performedAt = $row['performedAt'];
            /** @var numeric $tonnage */
            $tonnage = $row['tonnage'];

            $result[] = [
                'performedAt' => $performedAt,
                'tonnage' => (float) $tonnage,
            ];
        }

        return $result;
    }
}
