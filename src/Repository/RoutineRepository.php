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

    public function findByOwnerOrderedByDate(User $user): QueryBuilder
    {
        return $this->createQueryBuilder('r')
            ->where('r.owner = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC');
    }
}
