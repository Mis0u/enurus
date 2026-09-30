<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\Snapshot\YearInReviewSnapshot;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Résumé annuel « Ton année » d'un utilisateur, figé à sa publication (16 décembre, cf.
 * `YearInReviewCalendar`) : une séance ajoutée, modifiée ou antidatée ensuite ne le change plus,
 * éligibilité comprise. Créé pour tout compte existant à la publication, même sans séance, pour
 * afficher le message d'encouragement avec le nombre de séances figé.
 *
 * `emailedAt`, `seenAt` et `bannerDismissedAt` sont de vrais instants (envoi, ouverture,
 * fermeture du bandeau), donc avec fuseau, contrairement aux dates de séance.
 */
#[ORM\Entity(repositoryClass: YearInReviewRepository::class)]
#[ORM\Table(name: 'year_in_review')]
#[ORM\UniqueConstraint(name: 'UNIQ_YEAR_IN_REVIEW_OWNER_YEAR', columns: ['owner_id', 'year'])]
class YearInReview
{
    public const int MINIMUM_WORKOUT_COUNT = 5;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'yearInReviews')]
    #[ORM\JoinColumn(nullable: false)]
    public private(set) User $owner {
        get {
            return $this->owner;
        }
    }

    #[ORM\Column(type: Types::SMALLINT)]
    public private(set) int $year {
        get {
            return $this->year;
        }
    }

    #[ORM\Column(type: Types::INTEGER)]
    public private(set) int $workoutCount {
        get {
            return $this->workoutCount;
        }
    }

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $emailedAt = null {
        get {
            return $this->emailedAt;
        }
        set(?\DateTimeImmutable $emailedAt) {
            $this->emailedAt = $emailedAt;
        }
    }

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $seenAt = null {
        get {
            return $this->seenAt;
        }
        set(?\DateTimeImmutable $seenAt) {
            $this->seenAt = $seenAt;
        }
    }

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $bannerDismissedAt = null {
        get {
            return $this->bannerDismissedAt;
        }
        set(?\DateTimeImmutable $bannerDismissedAt) {
            $this->bannerDismissedAt = $bannerDismissedAt;
        }
    }

    /**
     * @var array<string, mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $snapshotData = null;

    private function __construct(User $owner, int $year, int $workoutCount)
    {
        $this->owner = $owner;
        $this->year = $year;
        $this->workoutCount = $workoutCount;
    }

    public static function eligible(User $owner, int $year, YearInReviewSnapshot $snapshot): self
    {
        $review = new self($owner, $year, $snapshot->totals->workoutCount);
        $review->snapshotData = $snapshot->toArray();

        return $review;
    }

    public static function notEligible(User $owner, int $year, int $workoutCount): self
    {
        return new self($owner, $year, $workoutCount);
    }

    public function isEligible(): bool
    {
        return null !== $this->snapshotData;
    }

    public function snapshot(): ?YearInReviewSnapshot
    {
        return null === $this->snapshotData ? null : YearInReviewSnapshot::fromArray($this->snapshotData);
    }

    public function missingWorkoutCount(): int
    {
        return max(0, self::MINIMUM_WORKOUT_COUNT - $this->workoutCount);
    }
}
