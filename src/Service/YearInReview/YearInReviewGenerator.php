<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Repository\YearInReviewRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Fige le résumé d'un utilisateur pour une année, une seule fois : relancer la génération (tâche
 * planifiée rejouée, commande manuelle) ne recalcule jamais un résumé existant.
 */
final readonly class YearInReviewGenerator
{
    public function __construct(
        private YearInReviewBuilder $builder,
        private YearInReviewRepository $yearInReviewRepository,
        private EntityManagerInterface $em,
    ) {
    }

    /**
     * @return YearInReview|null null si le résumé existait déjà
     */
    public function generate(User $user, int $year): ?YearInReview
    {
        if ($this->yearInReviewRepository->existsForOwnerAndYear($user, $year)) {
            return null;
        }

        $review = $this->builder->build($user, $year);
        $this->em->persist($review);
        $this->em->flush();

        return $review;
    }
}
