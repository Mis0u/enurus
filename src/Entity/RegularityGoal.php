<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Enum\Entity\RegularityGoal\RegularityGoalDurationEnum;
use App\Enum\Entity\RegularityGoal\RegularityGoalPeriodEnum;
use App\Repository\RegularityGoalRepository;
use App\Validator\NoDeloadOverlap;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Objectif de régularité : un défi daté, « X séances par jour ou par semaine pendant N semaines »,
 * créé depuis l'onglet Calendrier de Mes séances. Réussi si chaque période (jour ou semaine) atteint
 * son quota — une période couverte par un deload est neutre. Le défi va jusqu'à son dernier jour,
 * puis reste en historique avec son score : rien n'est stocké, tout est recalculé depuis les
 * séances par `RegularityGoalProgressCalculator`. Un seul objectif en cours (ou à venir) à la fois,
 * et jamais cumulé avec un deload (`NoDeloadOverlap`) : chaque jour du défi compte.
 *
 * `startDate` est la date choisie par l'utilisateur (date murale naïve, même décision que
 * `Workout::$performedAt`), et le défi démarre ce jour-là : pour un rythme hebdomadaire, ses
 * « semaines » sont des périodes de 7 jours à partir de cette date, pas des semaines calendaires
 * (un défi choisi à partir d'un mercredi démarrait sinon au lundi précédent, retour utilisateur).
 */
#[ORM\Entity(repositoryClass: RegularityGoalRepository::class)]
#[ORM\Table(name: 'regularity_goal')]
#[NoDeloadOverlap]
class RegularityGoal
{
    use TimestampTrait;

    private const int DAYS_PER_WEEK = 7;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'regularityGoals')]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner {
        get {
            return $this->owner;
        }
        set(User $owner) {
            $this->owner = $owner;
        }
    }

    #[ORM\Column(type: Types::SMALLINT)]
    #[Assert\Positive(message: 'regularity_goal.sessions.positive')]
    public int $sessionsPerPeriod = 3 {
        get {
            return $this->sessionsPerPeriod;
        }
        set(?int $sessionsPerPeriod) {
            $this->sessionsPerPeriod = $sessionsPerPeriod ?? $this->sessionsPerPeriod;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 10, enumType: RegularityGoalPeriodEnum::class)]
    public RegularityGoalPeriodEnum $period = RegularityGoalPeriodEnum::WEEK {
        get {
            return $this->period;
        }
        set(?RegularityGoalPeriodEnum $period) {
            $this->period = $period ?? $this->period;
        }
    }

    #[ORM\Column(type: Types::SMALLINT, enumType: RegularityGoalDurationEnum::class)]
    public RegularityGoalDurationEnum $duration = RegularityGoalDurationEnum::FOUR_WEEKS {
        get {
            return $this->duration;
        }
        set(?RegularityGoalDurationEnum $duration) {
            $this->duration = $duration ?? $this->duration;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank(message: 'regularity_goal.start_date.required')]
    public \DateTimeImmutable $startDate {
        get {
            return $this->startDate;
        }
        set(?\DateTimeImmutable $startDate) {
            $this->startDate = $startDate?->setTime(0, 0, 0) ?? $this->startDate;
        }
    }

    public function firstDay(): \DateTimeImmutable
    {
        return $this->startDate;
    }

    public function lastDay(): \DateTimeImmutable
    {
        return $this->firstDay()->modify(\sprintf('+%d days', $this->duration->value * self::DAYS_PER_WEEK - 1));
    }

    /**
     * Chevauche la plage de jours donnée (bornes incluses) : sert à interdire le cumul avec un
     * deload, dans les deux sens (`NoDeloadOverlap`, `NoRegularityGoalOverlap`).
     */
    public function overlaps(\DateTimeImmutable $start, \DateTimeImmutable $end): bool
    {
        if (! isset($this->startDate)) {
            return false;
        }

        return $this->firstDay() <= $end && $start <= $this->lastDay()->setTime(23, 59, 59);
    }

    /**
     * En cours ou à venir : pas encore passé dans l'historique.
     */
    public function isOngoingOn(\DateTimeImmutable $day): bool
    {
        return $this->lastDay() >= $day->setTime(0, 0, 0);
    }

    #[Assert\Callback]
    public function validateSessionsForPeriod(ExecutionContextInterface $context): void
    {
        if ($this->sessionsPerPeriod > $this->period->maxSessions()) {
            $context->buildViolation('regularity_goal.sessions.too_many')
                ->setParameter('{{ max }}', (string) $this->period->maxSessions())
                ->atPath('sessionsPerPeriod')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }

    /**
     * Jamais dans le passé : un défi démarré hier se jugerait sur des séances déjà faites.
     */
    #[Assert\Callback]
    public function validateStartNotInThePast(ExecutionContextInterface $context): void
    {
        if (isset($this->startDate) && $this->startDate < new \DateTimeImmutable('today')) {
            $context->buildViolation('regularity_goal.start_date.in_the_past')
                ->atPath('startDate')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
