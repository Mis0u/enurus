<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Enum\Entity\ProfileConnection\ProfileConnectionStatusEnum;
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

    /**
     * Demandes en attente et connexions acceptées où l'utilisateur est l'une des deux parties. Les
     * refusées et les révoquées ne sont pas listées : elles ne servent qu'au délai avant une
     * nouvelle demande. Les deux utilisateurs sont chargés avec la requête (jointures `ManyToOne`,
     * sans effet sur le nombre de lignes) pour ne déclencher aucune requête supplémentaire à
     * l'affichage.
     *
     * @return list<ProfileConnection>
     */
    public function findActiveInvolving(User $user): array
    {
        /** @var list<ProfileConnection> */
        return $this->createQueryBuilder('c')
            ->addSelect('requester', 'addressee')
            ->join('c.requester', 'requester')
            ->join('c.addressee', 'addressee')
            ->andWhere('c.requester = :user OR c.addressee = :user')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('user', $user)
            ->setParameter('statuses', [ProfileConnectionStatusEnum::PENDING, ProfileConnectionStatusEnum::ACCEPTED])
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingReceivedBy(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.addressee = :user')
            ->andWhere('c.status = :pending')
            ->setParameter('user', $user)
            ->setParameter('pending', ProfileConnectionStatusEnum::PENDING)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
