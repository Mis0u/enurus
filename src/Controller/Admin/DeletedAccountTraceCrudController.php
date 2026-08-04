<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DeletedAccountTrace;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Lecture seule — visibilité sur les comptes supprimés en attente de purge (6 mois, cf.
 * DeletedAccountTracePurgeCommand) et les réinscriptions détectées, sans exposer d'email en clair
 * (seul un hash SHA-256 est stocké, cf. AccountDeletionService).
 *
 * @extends AbstractCrudController<DeletedAccountTrace>
 */
#[IsGranted('ROLE_ADMIN')]
final class DeletedAccountTraceCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return DeletedAccountTrace::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.deleted_account_trace.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.deleted_account_trace.label.plural'))
            ->setDefaultSort([
                'deletedAt' => 'DESC',
            ])
            ->setTimezone('Europe/Paris')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('emailHash', $this->trans('admin.deleted_account_trace.field.email_hash'));
        yield DateTimeField::new('deletedAt', $this->trans('admin.deleted_account_trace.field.deleted_at'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', 'fr');
    }
}
