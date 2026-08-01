<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\RegistrationMilestoneSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ligne unique (seedée par migration, cf. RegistrationMilestoneSetting) — Action::NEW et
 * Action::DELETE désactivées, l'admin ne peut que modifier le pas existant.
 *
 * @extends AbstractCrudController<RegistrationMilestoneSetting>
 */
#[IsGranted('ROLE_ADMIN')]
final class RegistrationMilestoneSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return RegistrationMilestoneSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.registration_milestone.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.registration_milestone.label.plural'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield IntegerField::new('step', $this->trans('admin.registration_milestone.field.step'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', 'fr');
    }
}
