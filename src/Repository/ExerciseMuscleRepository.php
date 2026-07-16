<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ExerciseMuscle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExerciseMuscle>
 */
final class ExerciseMuscleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExerciseMuscle::class);
    }
}
