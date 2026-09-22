<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Repository\DeloadPeriodRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Semaine (ou période) de repos programmée par l'utilisateur — une semaine couverte par une
 * `DeloadPeriod` ne casse jamais la série de régularité même sans séance, cf.
 * `App\Service\Dashboard\DashboardRegularityService`. `startDate`/`endDate` sont des dates
 * murales saisies par l'utilisateur (même décision que `Workout::$performedAt`, cf. CLAUDE.md) :
 * `datetime_immutable` naïf, jamais `datetimetz`.
 */
#[ORM\Entity(repositoryClass: DeloadPeriodRepository::class)]
#[ORM\Table(name: 'deload_period')]
class DeloadPeriod
{
    use TimestampTrait;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    public User $owner {
        get {
            return $this->owner;
        }
        set(User $owner) {
            $this->owner = $owner;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank(message: 'deload_period.start_date.required')]
    public \DateTimeImmutable $startDate {
        get {
            return $this->startDate;
        }
        set(?\DateTimeImmutable $startDate) {
            $this->startDate = $startDate?->setTime(0, 0, 0) ?? $this->startDate;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank(message: 'deload_period.end_date.required')]
    public \DateTimeImmutable $endDate {
        get {
            return $this->endDate;
        }
        set(?\DateTimeImmutable $endDate) {
            $this->endDate = $endDate?->setTime(23, 59, 59) ?? $this->endDate;
        }
    }

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'deload_period.note_too_long')]
    public ?string $note = null {
        get {
            return $this->note;
        }
        set(?string $note) {
            $trimmed = null !== $note ? trim($note) : null;
            $this->note = '' !== $trimmed ? $trimmed : null;
        }
    }

    /**
     * @return bool Vrai si `$day` (n'importe quelle heure) tombe dans cette période.
     */
    public function covers(\DateTimeImmutable $day): bool
    {
        return $day >= $this->startDate && $day <= $this->endDate;
    }

    #[Assert\Callback]
    public function validateDateRange(ExecutionContextInterface $context): void
    {
        if (isset($this->startDate, $this->endDate) && $this->endDate < $this->startDate) {
            $context->buildViolation('deload_period.end_before_start')
                ->atPath('endDate')
                ->setTranslationDomain('validators')
                ->addViolation();
        }
    }
}
