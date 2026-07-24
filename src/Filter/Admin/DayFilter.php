<?php

declare(strict_types=1);

namespace App\Filter\Admin;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filtre "un jour entier" sur une colonne datetime — un unique sélecteur de date (sans heure),
 * qui matche toute valeur tombant dans cette journée, quelle que soit l'heure enregistrée.
 * `DateTimeFilter` d'EasyAdmin compare la date+heure exactes par défaut : sélectionner une date
 * sans heure y équivaut à minuit pile, ce qui ne matche jamais rien en pratique. Le champ produit
 * une valeur scalaire (pas de comparateur) : `EntityRepository::addFilterClause()` l'enveloppe
 * automatiquement en `{comparison: '=', value: ...}` avant d'appeler `apply()`, comparaison qu'on
 * ignore volontairement ici au profit d'un `BETWEEN` sur la journée.
 */
final class DayFilter implements FilterInterface
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
            ->setFormType(DateType::class)
            ->setFormTypeOptions([
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
        ;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();

        if (! $value instanceof \DateTimeImmutable) {
            return;
        }

        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $parameterName = $filterDataDto->getParameterName();
        $parameter2Name = $filterDataDto->getParameter2Name();
        $dayStart = $value->setTime(0, 0);

        $queryBuilder
            ->andWhere(\sprintf('%s.%s >= :%s', $alias, $property, $parameterName))
            ->andWhere(\sprintf('%s.%s < :%s', $alias, $property, $parameter2Name))
            ->setParameter($parameterName, $dayStart)
            ->setParameter($parameter2Name, $dayStart->modify('+1 day'))
        ;
    }
}
