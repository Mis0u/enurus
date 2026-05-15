<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workout;
use App\Enum\Entity\ExerciceMuscle\MuscleTypeEnum;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

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

    public function countByUserAndDate(User $user, DateTimeImmutable $start, DateTimeImmutable $end): int
    {
        return (int) $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->andWhere('w.owner = :user')
            ->andWhere('w.performedAt >= :start')
            ->andWhere('w.performedAt <= :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
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
     * @param string[] $workoutIds
     * @return array<string, float>
     */
    public function findTonnageByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        /** @var array<int, array{workoutId: mixed, tonnage: mixed}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select('w.id as workoutId, SUM(s.weight * s.reps) as tonnage')
            ->join('w.workoutExercises', 'we')
            ->join('we.exerciseSets', 's')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->groupBy('w.id')
            ->getQuery()
            ->getResult();

        $result = [];
        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutId */
            $workoutId = $row['workoutId'];
            /** @var numeric $tonnage */
            $tonnage = $row['tonnage'];

            $result[(string) $workoutId] = (float) $tonnage;
        }

        return $result;
    }

    /**
     * @param string[] $workoutIds
     * @return array<string, array<int, array{name: string, type: string}>>
     */
    public function findMusclesByWorkoutIds(array $workoutIds): array
    {
        if (empty($workoutIds)) {
            return [];
        }

        $rows = $this->fetchMuscleRows($workoutIds);
        $grouped = $this->deduplicateMuscles($rows);

        return $this->sortMusclesByType($grouped);
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

    /**
     * @param string[] $workoutIds
     * @return array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}>
     */
    private function fetchMuscleRows(array $workoutIds): array
    {
        /** @var array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}> $rows */
        $rows = $this->createQueryBuilder('w')
            ->select(
                'w.id as workoutId',
                'mg.name as muscleName',
                'mg.position as musclePosition',
                'em.type as muscleType'
            )
            ->join('w.workoutExercises', 'we')
            ->join('we.exercise', 'e')
            ->join('e.exerciseMuscles', 'em')
            ->join('em.muscleGroup', 'mg')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $workoutIds)
            ->orderBy('mg.position', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @param array<int, array{workoutId: mixed, muscleName: string, musclePosition: mixed, muscleType: mixed}> $rows
     * @return array<string, array<string, string>>
     */
    private function deduplicateMuscles(array $rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            /** @var string|\Stringable $workoutId */
            $workoutId = $row['workoutId'];

            /** @var mixed $type */
            $type = $row['muscleType'];

            $wid = (string) $workoutId;
            $name = $row['muscleName'];
            if ($type instanceof \BackedEnum) {
                $typeValue = (string) $type->value;
            } else {
                /** @var string $type */
                $typeValue = $type;
            }

            if (! isset($grouped[$wid][$name])) {
                $grouped[$wid][$name] = $typeValue;
            } elseif ($typeValue === MuscleTypeEnum::PRIMARY->value) {
                $grouped[$wid][$name] = MuscleTypeEnum::PRIMARY->value;
            }
        }

        return $grouped;
    }

    /**
     * @param array<string, array<string, string>> $grouped
     * @return array<string, array<int, array{name: string, type: string}>>
     */
    private function sortMusclesByType(array $grouped): array
    {
        $result = [];

        foreach ($grouped as $wid => $muscles) {
            $primaries = [];
            $secondaries = [];

            foreach ($muscles as $name => $type) {
                if ($type === MuscleTypeEnum::PRIMARY->value) {
                    $primaries[] = [
                        'name' => $name,
                        'type' => MuscleTypeEnum::PRIMARY->value,
                    ];
                } else {
                    $secondaries[] = [
                        'name' => $name,
                        'type' => MuscleTypeEnum::SECONDARY->value,
                    ];
                }
            }

            $result[$wid] = array_merge($primaries, $secondaries);
        }

        return $result;
    }
}
