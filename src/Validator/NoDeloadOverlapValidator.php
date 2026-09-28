<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\DeloadPeriod;
use App\Entity\RegularityGoal;
use App\Repository\DeloadPeriodRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class NoDeloadOverlapValidator extends ConstraintValidator
{
    public function __construct(
        private readonly DeloadPeriodRepository $deloadPeriodRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof NoDeloadOverlap) {
            throw new UnexpectedTypeException($constraint, NoDeloadOverlap::class);
        }

        if (! $value instanceof RegularityGoal) {
            throw new UnexpectedValueException($value, RegularityGoal::class);
        }

        $overlapsDeload = array_any(
            $this->deloadPeriodRepository->findByOwnerOrderedByStartDate($value->owner),
            static fn (DeloadPeriod $deload): bool => $value->overlaps($deload->startDate, $deload->endDate),
        );

        if ($overlapsDeload) {
            $this->context->buildViolation($constraint->message)
                ->atPath('startDate')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
