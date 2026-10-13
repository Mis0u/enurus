<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Exercise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Exercise>
 */
class ExerciseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Exercise::class);
    }

    /**
     * Returns all public exercises.
     * No SQL ordering — names are translation keys, sorting must happen
     * after translation in memory.
     *
     * @return list<Exercise>
     */
    public function findPublicExercises(): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->where('e.isPublic = :public')
            ->setParameter('public', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Exercices publics des fiches exercices, avec leurs muscles (silhouette, regroupement par
     * muscle) chargés en une requête.
     *
     * @param list<string> $names noms en base, qui sont des clés de traduction (`bench_press_bar.name`)
     *
     * @return list<Exercise>
     */
    public function findPublicWithMusclesByNames(array $names): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->addSelect('em', 'mg')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->where('e.isPublic = :public')
            ->andWhere('e.archived = :archived')
            ->andWhere('e.name IN (:names)')
            ->setParameter('public', true)
            ->setParameter('archived', false)
            ->setParameter('names', $names)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all custom exercises created by a specific user (archived excluded — cf.
     * ExerciseDeletionService).
     *
     * @return list<Exercise>
     */
    public function findCustomExercisesByUser(User $user): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->where('e.isPublic = :public')
            ->andWhere('e.owner = :owner')
            ->andWhere('e.archived = :archived')
            ->setParameter('public', false)
            ->setParameter('owner', $user)
            ->setParameter('archived', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns all exercises available for a user (public exercises + the user's own custom
     * exercises), archived exercises excluded — they're no longer offered for new use, cf.
     * ExerciseDeletionService.
     *
     * @return list<Exercise>
     */
    public function findAvailableForUser(User $user): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->addSelect('mg')
            ->where('(e.isPublic = :public OR e.owner = :owner)')
            ->andWhere('e.archived = :archived')
            ->setParameter('public', true)
            ->setParameter('owner', $user)
            ->setParameter('archived', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the user's archived custom exercises — only shown in the dedicated "Archivés"
     * filter of the library, never in the pickers.
     *
     * @return list<Exercise>
     */
    public function findArchivedByUser(User $user): array
    {
        /** @var list<Exercise> */
        return $this->createQueryBuilder('e')
            ->leftJoin('e.exerciseMuscles', 'em')
            ->addSelect('em')
            ->leftJoin('em.muscleGroup', 'mg')
            ->addSelect('mg')
            ->where('e.owner = :owner')
            ->andWhere('e.archived = :archived')
            ->setParameter('owner', $user)
            ->setParameter('archived', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Valeurs distinctes pour peupler le sélecteur du filtre admin "nom" — restreint aux exercices
     * personnalisés (`owner IS NOT NULL`), même périmètre que `ExerciseCrudController::createIndexQueryBuilder()`.
     *
     * @return list<string>
     */
    public function findDistinctPersonalizedNames(): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('DISTINCT e.name AS name')
            ->where('e.owner IS NOT NULL')
            ->orderBy('e.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_column($rows, 'name');
    }
}
