<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Un objectif de régularité ne chevauche jamais une semaine de repos programmée : un objectif reste
 * un objectif, chaque jour du défi compte. Pendant de `NoRegularityGoalOverlap`.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class NoDeloadOverlap extends Constraint
{
    public string $message = 'regularity_goal.deload_overlap';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
