<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Routine;
use App\Entity\User;
use App\Entity\Workout;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Filter\Admin\DayFilter;
use App\Repository\UserRepository;
use App\Service\Entity\WorkoutPhotoService;
use Doctrine\ORM\EntityManagerInterface;
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
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<Workout>
 */
#[IsGranted('ROLE_ADMIN')]
final class WorkoutCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly WorkoutPhotoService $workoutPhotoService,
        private readonly UserRepository $userRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Workout::class;
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);

        $adminIds = $this->userRepository->findAdminIds();

        if ([] !== $adminIds) {
            $qb->andWhere('entity.owner NOT IN (:adminIds)')->setParameter('adminIds', $adminIds);
        }

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.workout.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.workout.label.plural'))
            ->setDefaultSort([
                'performedAt' => 'DESC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('owner.email', $this->trans('admin.workout.field.owner'));
        yield DateField::new('performedAt', $this->trans('admin.workout.field.performed_at'));
        yield IntegerField::new('duration', $this->trans('admin.workout.field.duration'));
        yield AssociationField::new('routine', $this->trans('admin.workout.field.routine'))
            ->setCrudController(RoutineCrudController::class)
        ;
        yield TextareaField::new('note', $this->trans('admin.workout.field.note'))
            ->onlyOnDetail()
        ;
        yield Field::new('photoPath', $this->trans('admin.workout.field.photo'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/workout/_photo.html.twig')
        ;
        /**
         * Lecture seule — les exercices d'une séance se gèrent depuis l'app, pas depuis l'admin.
         * Même pattern que `RoutineCrudController::$routineExercises`.
         */
        yield Field::new('workoutExercises', $this->trans('admin.workout.field.exercises'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/workout/_exercises.html.twig')
        ;
    }

    /**
     * Voir + supprimer, jamais éditer — les données d'une séance sont saisies par l'utilisateur
     * lui-même, les corriger à sa place n'a pas de sens métier (contrairement à un cas exceptionnel
     * de suppression, ex. contenu abusif dans la note).
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
            ->add(
                EntityFilter::new('owner', $this->trans('admin.workout.field.owner'))
                    ->setFormTypeOption('value_type_options', [
                        'choice_label' => static fn (User $user): string => $user->email,
                    ])
            )
            ->add(DayFilter::new('performedAt', $this->trans('admin.workout.field.performed_at')))
            ->add(NumericFilter::new('duration', $this->trans('admin.workout.field.duration')))
            ->add(
                EntityFilter::new('routine', $this->trans('admin.workout.field.routine'))
                    ->setFormTypeOption('value_type_options', [
                        'choice_label' => static fn (Routine $routine): string => $routine->name,
                    ])
            )
        ;
    }

    /**
     * Nettoyage du fichier photo à la suppression — même pattern que pour les images de messagerie
     * (`ContactThreadCrudController::deleteEntity()`), réutilise `WorkoutPhotoService::deletePhoto()`
     * déjà utilisé côté app.
     */
    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        parent::deleteEntity($entityManager, $entityInstance);

        $this->workoutPhotoService->deletePhoto($entityInstance);
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', LocaleAllowedEnum::FR->value);
    }
}
