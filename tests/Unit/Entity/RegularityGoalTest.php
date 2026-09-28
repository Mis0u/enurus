<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\RegularityGoal;
use App\Entity\User;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Repository\DeloadPeriodRepository;
use App\Validator\NoDeloadOverlapValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintValidatorFactory;
use Symfony\Component\Validator\Validation;

final class RegularityGoalTest extends TestCase
{
    /**
     * Retour utilisateur : un défi hebdomadaire choisi « à partir du 30 septembre » démarrait au
     * lundi 28. Il démarre à la date choisie, ses semaines sont des périodes de 7 jours.
     */
    public function testAWeeklyGoalStartsOnTheChosenDay(): void
    {
        $goal = $this->goal(RegularityGoalPeriodEnum::WEEK, 3, '2026-09-30');

        self::assertSame('2026-09-30', $goal->firstDay()->format('Y-m-d'));
        self::assertSame('2026-10-27', $goal->lastDay()->format('Y-m-d'));
    }

    public function testADailyGoalStartsOnTheChosenDay(): void
    {
        $goal = $this->goal(RegularityGoalPeriodEnum::DAY, 1, '2026-10-08', RegularityGoalDurationEnum::TWO_WEEKS);

        self::assertSame('2026-10-08', $goal->firstDay()->format('Y-m-d'));
        self::assertSame('2026-10-21', $goal->lastDay()->format('Y-m-d'));
    }

    public function testAGoalIsOngoingUntilItsLastDayIncluded(): void
    {
        $goal = $this->goal(RegularityGoalPeriodEnum::WEEK, 3, '2026-10-05', RegularityGoalDurationEnum::ONE_WEEK);

        self::assertTrue($goal->isOngoingOn(new \DateTimeImmutable('2026-10-11 22:00')));
        self::assertFalse($goal->isOngoingOn(new \DateTimeImmutable('2026-10-12')));
    }

    /**
     * Bornes incluses : un repos qui finit la veille du défi, ou démarre le lendemain de sa fin, ne
     * le chevauche pas ; un seul jour commun suffit à le chevaucher.
     */
    public function testOverlapIncludesBothBoundsButNotTheAdjacentDays(): void
    {
        $goal = $this->goal(RegularityGoalPeriodEnum::WEEK, 3, '2026-10-05', RegularityGoalDurationEnum::ONE_WEEK);

        self::assertFalse($goal->overlaps(new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-10-04 23:59:59')));
        self::assertFalse($goal->overlaps(new \DateTimeImmutable('2026-10-12'), new \DateTimeImmutable('2026-10-18 23:59:59')));
        self::assertTrue($goal->overlaps(new \DateTimeImmutable('2026-10-11'), new \DateTimeImmutable('2026-10-18 23:59:59')));
        self::assertTrue($goal->overlaps(new \DateTimeImmutable('2026-09-28'), new \DateTimeImmutable('2026-10-05 23:59:59')));
    }

    public function testTooManySessionsForTheRhythmAreRejected(): void
    {
        self::assertSame(['sessionsPerPeriod'], $this->violatedPaths($this->goal(RegularityGoalPeriodEnum::DAY, 4, 'today')));
        self::assertSame([], $this->violatedPaths($this->goal(RegularityGoalPeriodEnum::WEEK, 14, 'today')));
    }

    /**
     * Un défi démarré dans le passé se jugerait sur des séances déjà faites.
     */
    public function testADailyGoalCannotStartInThePast(): void
    {
        self::assertSame(['startDate'], $this->violatedPaths($this->goal(RegularityGoalPeriodEnum::DAY, 1, 'yesterday')));
        self::assertSame([], $this->violatedPaths($this->goal(RegularityGoalPeriodEnum::DAY, 1, 'tomorrow')));
    }

    public function testAWeeklyGoalCannotStartInThePastEither(): void
    {
        self::assertSame(['startDate'], $this->violatedPaths($this->goal(RegularityGoalPeriodEnum::WEEK, 3, 'yesterday')));
        self::assertSame([], $this->violatedPaths($this->goal(RegularityGoalPeriodEnum::WEEK, 3, 'today')));
    }

    private function goal(
        RegularityGoalPeriodEnum $period,
        int $sessions,
        string $startDate,
        RegularityGoalDurationEnum $duration = RegularityGoalDurationEnum::FOUR_WEEKS,
    ): RegularityGoal {
        $goal = new RegularityGoal();
        $goal->owner = new User();
        $goal->period = $period;
        $goal->sessionsPerPeriod = $sessions;
        $goal->duration = $duration;
        $goal->startDate = new \DateTimeImmutable($startDate);

        return $goal;
    }

    /**
     * @return list<string>
     */
    private function violatedPaths(RegularityGoal $goal): array
    {
        // Aucun repos programmé : seules les règles propres à l'objectif sont en jeu ici.
        $deloadPeriodRepository = $this->createStub(DeloadPeriodRepository::class);
        $deloadPeriodRepository->method('findByOwnerOrderedByStartDate')->willReturn([]);
        $validatorFactory = new ConstraintValidatorFactory([
            NoDeloadOverlapValidator::class => new NoDeloadOverlapValidator($deloadPeriodRepository),
        ]);

        $violations = Validation::createValidatorBuilder()
            ->enableAttributeMapping()
            ->setConstraintValidatorFactory($validatorFactory)
            ->getValidator()
            ->validate($goal);
        $paths = [];

        foreach ($violations as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }
}
