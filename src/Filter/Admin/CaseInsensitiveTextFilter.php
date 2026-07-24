<?php

declare(strict_types=1);

namespace App\Filter\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Recherche texte ("contient") insensible à la casse sur une propriété directe de l'entité —
 * `TextFilter` d'EasyAdmin génère un `LIKE` sensible à la casse sous Postgres, ce qui rend la
 * recherche peu utilisable en pratique (il faut connaître la casse exacte stockée). `ILIKE`
 * (Postgres) n'existe pas en DQL standard — `LOWER()` des deux côtés est la façon portable de
 * faire, DQL la traduit correctement. Volontairement un seul input, sans sélecteur d'opérateur
 * (contient/commence par/…) — la recherche simple suffit pour ces usages admin.
 */
final class CaseInsensitiveTextFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TextType::class)
            ->setFormTypeOptions([
                'required' => false,
            ])
        ;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();

        if (! \is_string($value) || '' === $value) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $parameterName = $filterDataDto->getParameterName();

        $queryBuilder
            ->andWhere(\sprintf('LOWER(%s.%s) LIKE LOWER(:%s)', $alias, $property, $parameterName))
            ->setParameter($parameterName, '%' . $value . '%')
        ;
    }
}
