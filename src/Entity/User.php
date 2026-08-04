<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Enum\Contact\ContactRestrictionDurationEnum;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Exception;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'user.email_exists')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    use TimestampTrait;

    public const int NICKNAME_MIN_LENGTH = 3;

    public const int NICKNAME_MAX_LENGTH = 20;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    public ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    /**
     * @var Collection<int, Exercise>
     */
    #[ORM\OneToMany(targetEntity: Exercise::class, mappedBy: 'owner', cascade: ['remove'], orphanRemoval: true)]
    public Collection $exercises {
        get {
            return $this->exercises;
        }
    }

    /**
     * @var Collection<int, Workout>
     */
    #[ORM\OneToMany(targetEntity: Workout::class, mappedBy: 'owner', cascade: ['remove'], orphanRemoval: true)]
    public Collection $workouts {
        get {
            return $this->workouts;
        }
    }

    /**
     * @var Collection<int, Routine>
     */
    #[ORM\OneToMany(targetEntity: Routine::class, mappedBy: 'owner', cascade: ['remove'], orphanRemoval: true)]
    public Collection $routines {
        get {
            return $this->routines;
        }
    }

    /**
     * @var Collection<int, ContactThread>
     */
    #[ORM\OneToMany(targetEntity: ContactThread::class, mappedBy: 'owner', cascade: ['remove'], orphanRemoval: true)]
    public Collection $contactThreads {
        get {
            return $this->contactThreads;
        }
    }

    #[ORM\Column(length: 180, nullable: false)]
    #[NotBlank]
    #[Email]
    #[Length(max: 180)]
    public string $email {
        get {
            return $this->email;
        }
        set(string $email) {
            $this->email = $email;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 10, nullable: false, enumType: GenderEnum::class)]
    #[NotBlank]
    public GenderEnum $gender = GenderEnum::MALE {
        get {
            return $this->gender;
        }
        set(GenderEnum $gender) {
            $this->gender = $gender;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    public ?string $avatarPath = null {
        get {
            return $this->avatarPath;
        }
        set(?string $avatarPath) {
            $this->avatarPath = $avatarPath;
        }
    }

    #[ORM\Column]
    #[Ignore]
    public string $password {
        set(#[\SensitiveParameter] string $password) {
            $this->password = $password;
        }
    }

    #[ORM\Column]
    public bool $isVerified = false {
        get {
            return $this->isVerified;
        }
        set(bool $isVerified) {
            $this->isVerified = $isVerified;
        }
    }

    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    public \DateTimeImmutable $lastLogin {
        get {
            return $this->lastLogin;
        }
        set(\DateTimeImmutable $lastLogin) {
            $this->lastLogin = $lastLogin;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 5, nullable: false)]
    public string $locale {
        get {
            return $this->locale;
        }
        set(string $locale) {
            $this->locale = $locale;
        }
    }

    #[ORM\Column(type: Types::STRING, length: 25, nullable: false)]
    #[NotBlank]
    #[Length(
        min: self::NICKNAME_MIN_LENGTH,
        max: self::NICKNAME_MAX_LENGTH,
        minMessage: 'user.nickname.length.min',
        maxMessage: 'user.nickname.length.max',
    )]
    public string $nickname {
        get {
            return $this->nickname;
        }
        set(string $nickname) {
            $this->nickname = $nickname;
        }
    }

    #[NotNull]
    #[ORM\Column(type: 'string', length: 3, nullable: false, enumType: UnitOfMeasureEnum::class)]
    public UnitOfMeasureEnum $unitOfMeasure = UnitOfMeasureEnum::KG {
        get {
            return $this->unitOfMeasure;
        }
        set(UnitOfMeasureEnum $unitOfMeasure) {
            $this->unitOfMeasure = $unitOfMeasure;
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $deletionRequestedAt = null {
        get {
            return $this->deletionRequestedAt;
        }
        set(?\DateTimeImmutable $deletionRequestedAt) {
            $this->deletionRequestedAt = $deletionRequestedAt;
        }
    }

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $contactRestrictedUntil = null {
        get {
            return $this->contactRestrictedUntil;
        }
        set(?\DateTimeImmutable $contactRestrictedUntil) {
            $this->contactRestrictedUntil = $contactRestrictedUntil;
        }
    }

    #[ORM\Column]
    public bool $contactRestrictedPermanently = false {
        get {
            return $this->contactRestrictedPermanently;
        }
        set(bool $contactRestrictedPermanently) {
            $this->contactRestrictedPermanently = $contactRestrictedPermanently;
        }
    }

    #[ORM\Column(type: 'string', enumType: ContactRestrictionDurationEnum::class, nullable: true)]
    public ?ContactRestrictionDurationEnum $contactRestrictionDuration = null {
        get {
            return $this->contactRestrictionDuration;
        }
        set(?ContactRestrictionDurationEnum $contactRestrictionDuration) {
            $this->contactRestrictionDuration = $contactRestrictionDuration;
        }
    }

    /**
     * Propriété virtuelle non persistée — dérivée des deux champs ci-dessus, jamais stockée en
     * base (une restriction "1 semaine" ne doit pas nécessiter de job pour se lever d'elle-même).
     */
    public bool $isContactRestricted {
        get {
            if ($this->contactRestrictedPermanently) {
                return true;
            }

            return null !== $this->contactRestrictedUntil && $this->contactRestrictedUntil > new \DateTimeImmutable();
        }
    }

    /**
     * Blocage du compte (connexion) — distinct de la restriction de messagerie ci-dessus. Pas de
     * durée pour le moment : levée uniquement manuelle par un admin (cf. BlockedUserChecker,
     * UserAccountBlockService).
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    public ?\DateTimeImmutable $accountBlockedAt = null {
        get {
            return $this->accountBlockedAt;
        }
        set(?\DateTimeImmutable $accountBlockedAt) {
            $this->accountBlockedAt = $accountBlockedAt;
        }
    }

    public bool $isAccountBlocked {
        get {
            return null !== $this->accountBlockedAt;
        }
    }

    /**
     * @var string[] $roles
     */
    #[ORM\Column]
    private array $roles = [];

    public function __construct()
    {
        $this->exercises = new ArrayCollection();
        $this->workouts = new ArrayCollection();
        $this->routines = new ArrayCollection();
        $this->contactThreads = new ArrayCollection();
        $this->locale = LocaleAllowedEnum::EN->value;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        if ('' === $this->email) {
            throw new Exception();
        }
        return $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    #[\Deprecated]
    public function eraseCredentials(): void
    {
        // @deprecated, to be removed when upgrading to Symfony 8
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }
}
