<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ContactPollOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ContactPollOption>
 */
final class ContactPollOptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ContactPollOption::class);
    }
}
