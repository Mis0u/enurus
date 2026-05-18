<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
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
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
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

    /**
     * @var Collection<int, Exercise>
     */
    #[ORM\OneToMany(targetEntity: Exercise::class, mappedBy: 'owner')]
    public Collection $exercises {
        get {
            return $this->exercises;
        }
    }

    /**
     * @var Collection<int, Workout>
     */
    #[ORM\OneToMany(targetEntity: Workout::class, mappedBy: 'owner')]
    public Collection $workouts {
        get {
            return $this->workouts;
        }
    }

    /**
     * @var Collection<int, Routine>
     */
    #[ORM\OneToMany(targetEntity: Routine::class, mappedBy: 'owner')]
    public Collection $routines {
        get {
            return $this->routines;
        }
    }

    #[ORM\Column(length: 180, nullable: false)]
    #[NotBlank]
    #[Email]
    public string $email {
        get {
            return $this->email;
        }
        set(string $email) {
            $this->email = $email;
        }
    }

    #[ORM\Column(length: 10, nullable: false)]
    #[NotBlank]
    public string $gender {
        get {
            return $this->gender;
        }
        set(string $gender) {
            $this->gender = $gender;
        }
    }

    #[ORM\Column]
    #[Ignore]
    public string $password {
        set(#[\SensitiveParameter] string $password) {
            $this->password = $password;
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
    #[Length(min: 3, max: 20, minMessage: 'user.nickname.length.min', maxMessage: 'user.nickname.length.max')]
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
