<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProfileConnection;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfileConnection>
 */
class ProfileConnectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfileConnection::class);
    }

    /**
     * Cherche dans les deux sens. Une seule ligne par paire est garantie par
     * `ProfileConnectionRequestService` (pas par la base) : `setMaxResults(1)` évite une exception
     * si deux demandes simultanées en sens opposé avaient quand même créé deux lignes.
     */
    public function findBetween(User $first, User $second): ?ProfileConnection
    {
        /** @var ProfileConnection|null */
        return $this->createQueryBuilder('c')
            ->andWhere('(c.requester = :first AND c.addressee = :second) OR (c.requester = :second AND c.addressee = :first)')
            ->setParameter('first', $first)
            ->setParameter('second', $second)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
