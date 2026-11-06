<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Routine;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<Routine>
 */
final class RoutineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Routine::class);
    }

    /**
     * Vérifie si une routine avec ce nom existe déjà pour cet user.
     * En édition, on exclut la routine courante via $excludeId.
     */
    public function existsByNameForUser(string $name, User $user, ?Uuid $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('1')
            ->where('r.owner = :user')
            ->andWhere('r.archived = false')
            ->andWhere('LOWER(r.name) = LOWER(:name)')
            ->setParameter('user', $user)
            ->setParameter('name', $name)
            ->setMaxResults(1);

        if (null !== $excludeId) {
            $qb->andWhere('r.id != :excludeId')
                ->setParameter('excludeId', $excludeId, 'uuid');
        }

        return null !== $qb->getQuery()->getOneOrNullResult();
    }

    public function findActiveByOwnerOrderedByDate(User $user): QueryBuilder
    {
        return $this->byOwnerOrderedByDate($user, false);
    }

    public function findArchivedByOwnerOrderedByDate(User $user): QueryBuilder
    {
        return $this->byOwnerOrderedByDate($user, true);
    }

    public function countArchivedByOwner(User $user): int
    {
        return $this->count([
            'owner' => $user,
            'archived' => true,
        ]);
    }

    /**
     * Valeurs distinctes pour peupler le sélecteur du filtre admin "nom".
     *
     * @return list<string>
     */
    public function findDistinctNames(): array
    {
        /** @var list<array{name: string}> $rows */
        $rows = $this->createQueryBuilder('r')
            ->select('DISTINCT r.name AS name')
            ->orderBy('r.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_column($rows, 'name');
    }

    private function byOwnerOrderedByDate(User $user, bool $archived): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->where('r.owner = :user')
            ->andWhere('r.archived = :archived')
            ->setParameter('user', $user)
            ->setParameter('archived', $archived)
            ->orderBy('r.createdAt', 'DESC');
    }
}
