<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Trait\TimestampTrait;
use App\Repository\MuscleGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MuscleGroupRepository::class)]
class MuscleGroup
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

    #[ORM\Column(length: 100)]
    public string $name {
        get {
            return $this->name;
        }
        set(string $name) {
            $this->name = $name;
        }
    }

    #[ORM\Column]
    public int $position {
        get {
            return $this->position;
        }
        set(int $position) {
            $this->position = $position;
        }
    }

    /**
     * @var array<int, string>
     */
    #[Assert\NotNull]
    #[Assert\All([
        new Assert\Type('string'),
        new Assert\NotBlank(),
        new Assert\Regex(
            pattern: '/^[a-z0-9\-]+$/',
            message: 'Each SVG ID must contain only lowercase letters, numbers and hyphens.'
        ),
    ])]
    #[ORM\Column(type: 'json', nullable: false, options: [
        'default' => '[]',
    ])]
    public array $svgIds = [] {
        get {
            return $this->svgIds;
        }
        set(array $svgIds) {
            $this->svgIds = $svgIds;
        }
    }
}
