<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\User;
use App\Entity\Workout;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Lecture de tout l'historique de séances d'un utilisateur pour l'export, par lots : la mémoire
 * reste celle d'un lot, quelle que soit la taille de l'historique (sinon un export de plusieurs
 * années dépasse la limite mémoire de PHP). Les identifiants sont lus d'abord, puis chaque lot est
 * chargé complet par `IN` — jamais `setMaxResults()` sur une jointure de collection, qui couperait
 * des séries.
 *
 * Non finale : doublée dans `WorkoutExportFileGeneratorTest`.
 */
class WorkoutExportRepository
{
    private const int DEFAULT_BATCH_SIZE = 100;

    public function __construct(
        private readonly WorkoutRepository $workoutRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $batchSize = self::DEFAULT_BATCH_SIZE,
    ) {
    }

    /**
     * Séances dans l'ordre chronologique, exercices et séries chargés et triés par position.
     * Doctrine est vidé après chaque lot : `$user` et les séances déjà parcourues sont détachées.
     *
     * @return iterable<Workout>
     */
    public function iterateByUser(User $user): iterable
    {
        foreach (array_chunk($this->findWorkoutIdsByUser($user), max(1, $this->batchSize)) as $batchIds) {
            yield from $this->findCompleteWorkoutsByIds($batchIds);
            $this->entityManager->clear();
        }
    }

    /**
     * @return list<string>
     */
    private function findWorkoutIdsByUser(User $user): array
    {
        $ids = $this->workoutRepository->createQueryBuilder('w')
            ->select('w.id')
            ->andWhere('w.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('w.performedAt', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_values(array_map(static fn (mixed $id): string => \is_scalar($id) || $id instanceof \Stringable ? (string) $id : throw new \LogicException('Unexpected workout id.'), $ids));
    }

    /**
     * @param list<string> $ids
     *
     * @return list<Workout>
     */
    private function findCompleteWorkoutsByIds(array $ids): array
    {
        /** @var list<Workout> */
        return $this->workoutRepository->createQueryBuilder('w')
            ->addSelect('r', 'we', 'e', 's')
            ->leftJoin('w.routine', 'r')
            ->leftJoin('w.workoutExercises', 'we')
            ->leftJoin('we.exercise', 'e')
            ->leftJoin('we.exerciseSets', 's')
            ->andWhere('w.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('w.performedAt', 'ASC')
            ->addOrderBy('w.id', 'ASC')
            ->addOrderBy('we.position', 'ASC')
            ->addOrderBy('s.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
