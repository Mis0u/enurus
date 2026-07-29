<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ContactBroadcastRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * Snapshot d'un envoi groupé admin (`ContactThreadComposeService::composeToAudience()`) — un seul
 * enregistrement par envoi, quel que soit le nombre de destinataires. Chaque destinataire garde
 * son propre `ContactThread` (nécessaire pour son fil personnel et le Voter), relié à ce snapshot
 * via `ContactThread::$broadcast` pour un affichage groupé côté admin ("Diffusions").
 */
#[ORM\Entity(repositoryClass: ContactBroadcastRepository::class)]
class ContactBroadcast
{
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
    public User $sentBy {
        get {
            return $this->sentBy;
        }
        set(User $sentBy) {
            $this->sentBy = $sentBy;
        }
    }

    #[ORM\Column(length: 150)]
    public string $subject = '' {
        get {
            return $this->subject;
        }
        set(string $subject) {
            $this->subject = $subject;
        }
    }

    #[ORM\Column(type: 'text')]
    public string $body = '' {
        get {
            return $this->body;
        }
        set(string $body) {
            $this->body = $body;
        }
    }

    /**
     * Catégorie appliquée à chaque `ContactThread` fanned-out (cf. SendContactBroadcastMessageHandler)
     * — limitée en pratique à `INFORMATIVE` (diffusion classique) ou `VOTE` (sondage), jamais aux
     * catégories réservées aux fils initiés par un utilisateur (BUG, SUGGESTION, ...).
     */
    #[ORM\Column(type: 'string', enumType: ContactCategoryEnum::class)]
    public ContactCategoryEnum $category = ContactCategoryEnum::INFORMATIVE {
        get {
            return $this->category;
        }
        set(ContactCategoryEnum $category) {
            $this->category = $category;
        }
    }

    #[ORM\Column(type: 'string', enumType: ContactBroadcastTargetEnum::class)]
    public ContactBroadcastTargetEnum $target {
        get {
            return $this->target;
        }
        set(ContactBroadcastTargetEnum $target) {
            $this->target = $target;
        }
    }

    #[ORM\Column(type: 'string', enumType: LocaleAllowedEnum::class, nullable: true)]
    public ?LocaleAllowedEnum $locale = null {
        get {
            return $this->locale;
        }
        set(?LocaleAllowedEnum $locale) {
            $this->locale = $locale;
        }
    }

    #[ORM\Column]
    public int $recipientCount = 0 {
        get {
            return $this->recipientCount;
        }
        set(int $recipientCount) {
            $this->recipientCount = $recipientCount;
        }
    }

    #[ORM\Column(type: 'datetime_immutable')]
    public \DateTimeImmutable $sentAt {
        get {
            return $this->sentAt;
        }
    }

    /**
     * Renseigné uniquement pour une diffusion de catégorie `VOTE` — date de clôture du vote,
     * calculée à l'envoi à partir de la durée choisie par l'admin. La clôture est vérifiée en
     * temps réel (`pollClosesAt <= now()`), jamais par une tâche planifiée : pas de dérive possible
     * entre deux passages d'un cron.
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    public ?\DateTimeImmutable $pollClosesAt = null {
        get {
            return $this->pollClosesAt;
        }
        set(?\DateTimeImmutable $pollClosesAt) {
            $this->pollClosesAt = $pollClosesAt;
        }
    }

    /**
     * `cascade: remove` + `orphanRemoval` : supprimer une diffusion supprime aussi les fils
     * individuels qu'elle a créés chez chaque destinataire — ils n'existent que comme artefact de
     * cet envoi et ne sont jamais répondables (cf. ContactThreadVoter, catégories INFORMATIVE/VOTE).
     *
     * @var Collection<int, ContactThread>
     */
    #[ORM\OneToMany(targetEntity: ContactThread::class, mappedBy: 'broadcast', cascade: ['remove'], orphanRemoval: true)]
    public Collection $threads {
        get {
            return $this->threads;
        }
    }

    /**
     * Renseigné uniquement pour une diffusion de catégorie `VOTE` — options proposées, dans
     * l'ordre de saisie par l'admin (`position`).
     *
     * @var Collection<int, ContactPollOption>
     */
    #[ORM\OneToMany(targetEntity: ContactPollOption::class, mappedBy: 'broadcast', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'position' => 'ASC',
    ])]
    public Collection $pollOptions {
        get {
            return $this->pollOptions;
        }
    }

    public function __construct()
    {
        $this->sentAt = new \DateTimeImmutable();
        $this->threads = new ArrayCollection();
        $this->pollOptions = new ArrayCollection();
    }

    public function addPollOption(ContactPollOption $option): void
    {
        if (! $this->pollOptions->contains($option)) {
            $this->pollOptions->add($option);
            $option->broadcast = $this;
        }
    }

    public function isPoll(): bool
    {
        return ContactCategoryEnum::VOTE === $this->category;
    }

    public function isPollClosed(\DateTimeImmutable $now): bool
    {
        return null !== $this->pollClosesAt && $this->pollClosesAt <= $now;
    }

    /**
     * Jours pleins restants avant clôture, arrondis au jour supérieur dès qu'il reste une fraction
     * de journée (ex. 2 jours et 3 heures restants → 3). Retourne 0 si le vote est déjà clos ou
     * n'a pas de date de clôture — l'affichage doit alors basculer sur un message "se termine
     * aujourd'hui" plutôt que d'interpréter ce 0 comme une valeur négative.
     */
    public function daysUntilPollCloses(\DateTimeImmutable $now): int
    {
        if (null === $this->pollClosesAt || $this->pollClosesAt <= $now) {
            return 0;
        }

        $interval = $now->diff($this->pollClosesAt);

        if (false === $interval->days) {
            throw new \LogicException('DateInterval::$days is only false when not built via DateTimeImmutable::diff().');
        }

        $days = $interval->days;

        if (0 < $interval->h || 0 < $interval->i || 0 < $interval->s) {
            ++$days;
        }

        return $days;
    }
}
