<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DeloadPeriod;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DeloadPeriod>
 */
class DeloadPeriodRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DeloadPeriod::class);
    }

    /**
     * Toutes les périodes de l'utilisateur, les plus récentes en premier — utilisé à la fois pour
     * l'affichage (liste "Semaines de repos programmées") et pour le calcul de régularité
     * (`DashboardRegularityService`), qui a besoin de l'historique complet, pas seulement des
     * périodes à venir.
     *
     * @return list<DeloadPeriod>
     */
    public function findByOwnerOrderedByStartDate(User $owner): array
    {
        /** @var list<DeloadPeriod> */
        return $this->createQueryBuilder('d')
            ->andWhere('d.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('d.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
