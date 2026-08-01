<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ResetPasswordRequestRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordRequestTrait;

#[ORM\Entity(repositoryClass: ResetPasswordRequestRepository::class)]
class ResetPasswordRequest implements ResetPasswordRequestInterface
{
    use ResetPasswordRequestTrait;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    public User $user {
        get {
            return $this->user;
        }
    }

    /**
     * @var DateTimeImmutable $requestedAt
     */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    protected $requestedAt;

    /**
     * @var DateTimeImmutable $expiresAt
     */
    #[ORM\Column(type: Types::DATETIMETZ_IMMUTABLE)]
    protected $expiresAt;

    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null {
        get {
            return $this->id;
        }
    }

    public function __construct(User $user, \DateTimeInterface $expiresAt, string $selector, #[\SensitiveParameter] string $hashedToken)
    {
        $this->user = $user;
        $this->initialize($expiresAt, $selector, $hashedToken);
    }

    /**
     * Contrat imposé par {@see ResetPasswordRequestInterface} (bundle tiers) — le property hook
     * `$user` reste la vraie donnée, cette méthode ne fait que satisfaire l'interface.
     */
    public function getUser(): User
    {
        return $this->user;
    }
}
