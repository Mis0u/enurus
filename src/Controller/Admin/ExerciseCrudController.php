<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Exercise;
use App\Entity\User;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Repository\ExerciseRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Voir + supprimer, jamais éditer — à but de modération. Les exercices publics (`owner === null`)
 * sont hors scope (référentiel curaté par migration, cf. CLAUDE.md), seuls les exercices
 * personnalisés des users apparaissent ici, pour repérer et retirer un contenu inapproprié sans
 * donner de droit d'édition (un contenu à corriger doit être supprimé, pas réécrit par l'admin).
 *
 * @extends AbstractCrudController<Exercise>
 */
#[IsGranted('ROLE_ADMIN')]
final class ExerciseCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ExerciseRepository $exerciseRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Exercise::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.exercise.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.exercise.label.plural'))
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
        ;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.owner IS NOT NULL')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', $this->trans('admin.exercise.field.name'));
        yield TextField::new('owner.email', $this->trans('admin.exercise.field.owner'));
        yield TextareaField::new('description', $this->trans('admin.exercise.field.description'))->hideOnIndex();
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('name', $this->trans('admin.exercise.field.name'))->setChoices($this->nameChoices()))
            ->add(
                EntityFilter::new('owner', $this->trans('admin.exercise.field.owner'))
                    ->setFormTypeOption('value_type_options', [
                        'choice_label' => static fn (User $user): string => $user->email,
                    ])
            )
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', LocaleAllowedEnum::FR->value);
    }

    /**
     * @return array<string, string>
     */
    private function nameChoices(): array
    {
        $choices = [];

        foreach ($this->exerciseRepository->findDistinctPersonalizedNames() as $name) {
            $choices[$name] = $name;
        }

        return $choices;
    }
}
