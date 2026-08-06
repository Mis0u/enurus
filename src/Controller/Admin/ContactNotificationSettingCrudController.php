<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ContactNotificationSetting;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Ligne unique (seedée par migration, cf. ContactNotificationSetting) — Action::NEW et
 * Action::DELETE désactivées, l'admin ne peut que modifier le réglage existant.
 *
 * @extends AbstractCrudController<ContactNotificationSetting>
 */
#[IsGranted('ROLE_ADMIN')]
final class ContactNotificationSettingCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactNotificationSetting::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.contact_notification_setting.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.contact_notification_setting.label.plural'))
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        /**
         * `renderAsSwitch` ne devient un vrai toggle Ajax que sur la page index (comportement
         * EasyAdmin natif, cf. field-boolean.js) — sur les autres pages il s'affiche en simple
         * case à cocher. Un seul enregistrement existe (cf. classdoc), donc l'index EST le point
         * d'accès principal au réglage.
         */
        yield BooleanField::new('telegramNotificationsEnabled', $this->trans('admin.contact_notification_setting.field.telegram_notifications_enabled'))
            ->renderAsSwitch(true)
        ;
    }

    /**
     * Le switch de l'index suffit à piloter l'unique ligne de réglage (cf. `configureFields()`) —
     * pas besoin d'une page détail ni d'un formulaire d'édition complet. `Action::EDIT` reste
     * néanmoins autorisée globalement (retirée seulement de l'index) : `BooleanConfigurator`
     * s'appuie sur cette permission pour générer l'URL de bascule Ajax du switch, la désactiver
     * désactiverait le switch lui-même.
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::EDIT)
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', 'fr');
    }
}
