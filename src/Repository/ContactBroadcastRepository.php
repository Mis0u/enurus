<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactBroadcast;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactBroadcast>
 */
final class ContactBroadcastRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactBroadcast::class);
    }
}
