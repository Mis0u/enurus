<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\DeloadPeriod;
use App\Entity\RegularityGoal;
use App\Repository\RegularityGoalRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class NoRegularityGoalOverlapValidator extends ConstraintValidator
{
    public function __construct(
        private readonly RegularityGoalRepository $regularityGoalRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof NoRegularityGoalOverlap) {
            throw new UnexpectedTypeException($constraint, NoRegularityGoalOverlap::class);
        }

        if (! $value instanceof DeloadPeriod) {
            throw new UnexpectedValueException($value, DeloadPeriod::class);
        }

        // Dates manquantes : déjà signalées par leur propre contrainte NotBlank.
        if (! $value->hasDates()) {
            return;
        }

        $overlapsGoal = array_any(
            $this->regularityGoalRepository->findByOwnerNewestFirst($value->owner),
            static fn (RegularityGoal $goal): bool => $goal->overlaps($value->startDate, $value->endDate),
        );

        if ($overlapsGoal) {
            $this->context->buildViolation($constraint->message)
                ->atPath('startDate')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
