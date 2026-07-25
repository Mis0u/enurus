<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Routine;
use App\Entity\User;
use App\Filter\Admin\DayFilter;
use App\Repository\RoutineRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Routine>
 */
#[IsGranted('ROLE_ADMIN')]
final class RoutineCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly RoutineRepository $routineRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Routine::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.routine.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.routine.label.plural'))
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('name', $this->trans('admin.routine.field.name'));
        yield TextField::new('owner.email', $this->trans('admin.routine.field.owner'));
        yield DateTimeField::new('createdAt', $this->trans('admin.routine.field.created_at'));
        yield DateTimeField::new('updatedAt', $this->trans('admin.routine.field.updated_at'));
        yield TextareaField::new('description', $this->trans('admin.routine.field.description'))->hideOnIndex();
        /**
         * Lecture seule — les exercices d'une routine se gèrent depuis l'app (position, ajout,
         * retrait), pas depuis l'admin. Même pattern que `UserCrudController::$routines`.
         */
        yield Field::new('routineExercises', $this->trans('admin.routine.field.exercises'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/routine/_exercises.html.twig')
        ;
    }

    /**
     * Voir + supprimer, jamais éditer — mêmes raisons que `WorkoutCrudController` : données saisies
     * par l'utilisateur, un contenu à corriger doit être supprimé, pas réécrit par l'admin.
     */
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
            ->add(ChoiceFilter::new('name', $this->trans('admin.routine.field.name'))->setChoices($this->nameChoices()))
            ->add(
                EntityFilter::new('owner', $this->trans('admin.routine.field.owner'))
                    ->setFormTypeOption('value_type_options', [
                        'choice_label' => static fn (User $user): string => $user->email,
                    ])
            )
            ->add(DayFilter::new('createdAt', $this->trans('admin.routine.field.created_at')))
            ->add(DayFilter::new('updatedAt', $this->trans('admin.routine.field.updated_at')))
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', 'fr');
    }

    /**
     * @return array<string, string>
     */
    private function nameChoices(): array
    {
        $choices = [];

        foreach ($this->routineRepository->findDistinctNames() as $name) {
            $choices[$name] = $name;
        }

        return $choices;
    }
}
