<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Une semaine de repos ne se programme jamais pendant un objectif de régularité (passé, en cours ou
 * à venir). Pendant de `NoDeloadOverlap`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoRegularityGoalOverlap extends Constraint
{
    public string $message = 'deload_period.regularity_goal_overlap';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
