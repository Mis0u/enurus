<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Enum\Contact\ContactRestrictionDurationEnum;
use App\Enum\Entity\User\GenderEnum;
use App\Enum\Entity\User\UnitOfMeasureEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Filter\Admin\DayFilter;
use App\Form\Admin\ContactRestrictionFormType;
use App\Form\Admin\UserForceDeleteFormType;
use App\Repository\UserRepository;
use App\Service\Contact\ContactRestrictionService;
use App\Service\Entity\AccountDeletionService;
use App\Service\Security\ResetPasswordService;
use App\Service\Security\SessionInvalidationService;
use App\Service\Security\UserAccountBlockService;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Intl\IntlFormatterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NullFilter;
use EasyCorp\Bundle\EasyAdminBundle\Intl\IntlFormatter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<User>
 */
#[IsGranted('ROLE_ADMIN')]
final class UserCrudController extends AbstractCrudController
{
    private const string ACTION_CANCEL_DELETION = 'cancelDeletion';

    private const string ACTION_RESTRICT_CONTACT = 'restrictContact';

    private const string ACTION_LIFT_CONTACT_RESTRICTION = 'liftContactRestriction';

    private const string ACTION_BLOCK_ACCOUNT = 'blockAccount';

    private const string ACTION_UNBLOCK_ACCOUNT = 'unblockAccount';

    private const string ACTION_FORCE_LOGOUT = 'forceLogout';

    private const string ACTION_RESEND_RESET_EMAIL = 'resendResetEmail';

    private const string ACTION_FORCE_DELETE = 'forceDelete';

    public function __construct(
        private readonly AccountDeletionService $accountDeletionService,
        private readonly ContactRestrictionService $contactRestrictionService,
        private readonly UserAccountBlockService $userAccountBlockService,
        private readonly SessionInvalidationService $sessionInvalidationService,
        private readonly ResetPasswordService $resetPasswordService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly UserPasswordHasherInterface $userPasswordHasher,
        private readonly TranslatorInterface $translator,
        private readonly UserRepository $userRepository,
        /**
         * `IntlFormatterInterface` n'a pas d'alias de service public dans EasyAdminBundle (seule
         * la classe concrète `IntlFormatter` est enregistrée) — même pattern que
         * `EmailVerificationService::$verifyEmailHelper` pour un cas similaire.
         */
        #[Autowire(service: IntlFormatter::class)]
        private readonly IntlFormatterInterface $intlFormatter,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.user.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.user.label.plural'))
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ])
            ->setDefaultRowAction(Action::DETAIL)
            ->setTimezone('Europe/Paris')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('email');
        yield TextField::new('nickname', $this->trans('admin.user.field.nickname'));
        yield ChoiceField::new('gender', $this->trans('admin.user.field.gender'))->setChoices($this->enumChoices(GenderEnum::cases()));
        yield ChoiceField::new('locale', $this->trans('admin.user.field.locale'))->setChoices($this->localeChoices());
        yield ChoiceField::new('unitOfMeasure', $this->trans('admin.user.field.unit_of_measure'))->setChoices($this->enumChoices(UnitOfMeasureEnum::cases()));
        /**
         * Lecture seule — sert au support ("je n'arrive pas à me connecter") pour distinguer un
         * compte jamais vérifié d'un autre problème, sans exposer de bascule manuelle : forcer la
         * vérification depuis l'admin court-circuiterait `EmailVerificationService`, décision hors
         * scope ici. `renderAsSwitch(false)` est indispensable pour ça : `BooleanField` rend un
         * switch Ajax **par défaut** (contrairement à ce que `hideOnForm()` seul laisse penser),
         * qui modifierait la valeur en base au clic sans passer par le formulaire ni par
         * `EmailVerificationService` — même piège que `telegramNotificationsMuted` ci-dessous, sauf
         * que celui-là active le switch volontairement.
         */
        yield BooleanField::new('isVerified', $this->trans('admin.user.field.is_verified'))
            ->renderAsSwitch(false)
            ->hideOnForm()
        ;
        yield DateTimeField::new('createdAt', $this->trans('admin.user.field.created_at'))->hideOnForm();
        yield DateTimeField::new('lastLogin', $this->trans('admin.user.field.last_login'))->hideOnForm();
        yield DateTimeField::new('deletionRequestedAt', $this->trans('admin.user.field.deletion_requested_at'))->hideOnForm();
        yield DateTimeField::new('accountBlockedAt', $this->trans('admin.user.field.account_blocked_at'))->hideOnForm();
        /**
         * État de la restriction de messagerie (posée/levée via les actions `restrictContact`/
         * `liftContactRestriction` ci-dessous, jamais éditée directement ici) — sans ces champs,
         * un admin ne peut savoir ni jusqu'à quand ni sous quelle forme un compte est restreint une
         * fois l'action posée, seul le bouton "lever" reste visible (`isContactRestricted`). Visible
         * en liste (pas seulement en détail) comme `accountBlockedAt` ci-dessus, même besoin de
         * visibilité rapide. `formatValue` affiche "Permanent" plutôt que `null` quand
         * `contactRestrictedPermanently` est vrai — une restriction permanente ne porte jamais de
         * date de fin (cf. `ContactRestrictionService`). `formatValue` court-circuite le formatage
         * intl automatique de `DateTimeField` (il reçoit toujours la valeur brute, jamais la chaîne
         * déjà formatée) — la branche non permanente doit donc reformater elle-même la date via
         * `IntlFormatterInterface`, sous peine de renvoyer un `DateTimeImmutable` brut au template
         * (`Object of class DateTimeImmutable could not be converted to string`).
         */
        yield DateTimeField::new('contactRestrictedUntil', $this->trans('admin.user.field.contact_restricted_until'))
            ->formatValue(function (mixed $value, User $user): ?string {
                if ($user->contactRestrictedPermanently) {
                    return $this->trans('admin.contact_restriction.duration.permanent');
                }

                if (! $value instanceof \DateTimeInterface) {
                    return null;
                }

                return $this->intlFormatter->formatDateTime($value, 'medium', 'medium', '', 'Europe/Paris');
            })
        ;
        yield ChoiceField::new('contactRestrictionDuration', $this->trans('admin.user.field.contact_restriction_duration'))
            ->setChoices($this->enumChoices(ContactRestrictionDurationEnum::cases()))
            ->onlyOnDetail()
        ;
        /**
         * `renderAsSwitch` bascule la valeur en Ajax (PATCH) directement depuis la liste, sans
         * passer par la fiche détail — géré nativement par EasyAdmin (`BooleanConfigurator`),
         * flush inclus. `hideOnForm()` évite un doublon avec le formulaire d'édition complet.
         */
        yield BooleanField::new('telegramNotificationsMuted', $this->trans('admin.user.field.telegram_notifications_muted'))
            ->renderAsSwitch(true)
            ->hideOnForm()
        ;
        /**
         * Détail uniquement, à but de modération — même pattern que `WorkoutCrudController::$photoPath`
         * (`_photo.html.twig`) : un avatar signalé inapproprié doit rester visible depuis l'admin
         * sans avoir à requêter la base.
         */
        yield Field::new('avatarPath', $this->trans('admin.user.field.avatar'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/user/_avatar.html.twig')
        ;
        /**
         * Lecture seule, à but de support — pas de CRUD séparé pour Routines/Workouts, éditer les
         * données d'entraînement d'un utilisateur depuis l'admin n'a pas de sens métier. Même
         * pattern que `ContactThreadCrudController::$messages` : propriété réelle + template dédié,
         * `field.value` porte directement la collection.
         */
        yield Field::new('routines', $this->trans('admin.user.field.routines'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/user/_routines.html.twig')
        ;
        yield Field::new('workouts', $this->trans('admin.user.field.workouts'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/user/_workouts.html.twig')
        ;
    }

    /**
     * Les comptes ROLE_ADMIN ne doivent jamais apparaître ici, même en tant que simple ligne — un
     * admin qui bloque/déconnecte/supprime un autre compte admin par erreur est un risque trop
     * grand pour de la visibilité. `roles` est une colonne JSON, cf. `UserRepository::findAdminIds()`
     * pour pourquoi ce filtre n'est pas une simple condition DQL.
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $adminIds = $this->userRepository->findAdminIds();

        if ([] !== $adminIds) {
            $qb->andWhere('entity.id NOT IN (:adminIds)')->setParameter('adminIds', $adminIds);
        }

        return $qb;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('email', $this->trans('admin.user.filter.email'))->setChoices($this->emailChoices()))
            ->add(ChoiceFilter::new('nickname', $this->trans('admin.user.field.nickname'))->setChoices($this->nicknameChoices()))
            ->add(ChoiceFilter::new('locale', $this->trans('admin.user.field.locale'))->setChoices($this->localeChoices()))
            ->add(ChoiceFilter::new('gender', $this->trans('admin.user.field.gender'))->setChoices($this->enumChoices(GenderEnum::cases())))
            ->add(ChoiceFilter::new('unitOfMeasure', $this->trans('admin.user.field.unit_of_measure'))->setChoices($this->enumChoices(UnitOfMeasureEnum::cases())))
            ->add(BooleanFilter::new('isVerified', $this->trans('admin.user.field.is_verified')))
            ->add(DayFilter::new('createdAt', $this->trans('admin.user.field.created_at')))
            ->add(DayFilter::new('lastLogin', $this->trans('admin.user.field.last_login')))
            ->add(
                NullFilter::new('deletionRequestedAt', $this->trans('admin.user.field.deletion_requested_at'))
                    ->setChoiceLabels($this->trans('admin.user.filter.deletion_not_requested'), $this->trans('admin.user.filter.deletion_requested'))
            )
        ;
    }

    /**
     * Même garde qu'à l'index (cf. `createIndexQueryBuilder()`) mais pour l'accès direct par URL —
     * masquer la ligne dans la liste ne suffit pas, EasyAdmin résout l'entité de detail/edit par id
     * indépendamment de la requête d'index.
     */
    public function detail(AdminContext $context)
    {
        $this->getUserEntity($context);

        return parent::detail($context);
    }

    public function edit(AdminContext $context)
    {
        $this->getUserEntity($context);

        return parent::edit($context);
    }

    /**
     * NEW reste désactivé volontairement : créer un user ici court-circuiterait le flux
     * d'inscription (hash du mot de passe, email de bienvenue). Le DELETE natif d'EasyAdmin reste
     * désactivé aussi (pas de confirmation par mot de passe possible avec) au profit de l'action
     * `forceDelete` ci-dessous, réservée aux cas exceptionnels. ROLE_ADMIN n'est jamais éditable
     * ici — risque d'auto-déclassement/élévation de privilège via un simple formulaire, réservé à
     * une intervention directe en base.
     */
    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::DELETE)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_CANCEL_DELETION, $this->trans('admin.user.action.cancel_deletion'))
                    ->linkToCrudAction(self::ACTION_CANCEL_DELETION)
                    ->displayIf(static fn (User $user): bool => null !== $user->deletionRequestedAt)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_RESTRICT_CONTACT, $this->trans('admin.user.action.restrict_contact'))
                    ->linkToCrudAction(self::ACTION_RESTRICT_CONTACT)
                    ->displayIf(static fn (User $user): bool => ! $user->isContactRestricted)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_LIFT_CONTACT_RESTRICTION, $this->trans('admin.user.action.lift_contact_restriction'))
                    ->linkToCrudAction(self::ACTION_LIFT_CONTACT_RESTRICTION)
                    ->displayIf(static fn (User $user): bool => $user->isContactRestricted)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_BLOCK_ACCOUNT, $this->trans('admin.user.action.block_account'))
                    ->linkToCrudAction(self::ACTION_BLOCK_ACCOUNT)
                    ->displayIf(static fn (User $user): bool => ! $user->isAccountBlocked)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_UNBLOCK_ACCOUNT, $this->trans('admin.user.action.unblock_account'))
                    ->linkToCrudAction(self::ACTION_UNBLOCK_ACCOUNT)
                    ->displayIf(static fn (User $user): bool => $user->isAccountBlocked)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_FORCE_LOGOUT, $this->trans('admin.user.action.force_logout'))
                    ->linkToCrudAction(self::ACTION_FORCE_LOGOUT)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_RESEND_RESET_EMAIL, $this->trans('admin.user.action.resend_reset_email'))
                    ->linkToCrudAction(self::ACTION_RESEND_RESET_EMAIL)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_FORCE_DELETE, $this->trans('admin.user.action.force_delete'))
                    ->linkToCrudAction(self::ACTION_FORCE_DELETE)
            )
        ;
    }

    /**
     * @param AdminContext<User> $context
     */
    public function cancelDeletion(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'user_admin_cancel_deletion_' . $user->id);

            $this->accountDeletionService->cancelDeletion($user);
            $this->addFlash('success', $this->trans('admin.user.flash.deletion_cancelled'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/cancel_deletion.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function restrictContact(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        $form = $this->createForm(ContactRestrictionFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $choice */
            $choice = $form->get('duration')->getData();

            $permanent = ContactRestrictionFormType::CHOICE_PERMANENT === $choice;
            $duration = match ($choice) {
                ContactRestrictionFormType::CHOICE_ONE_WEEK => ContactRestrictionDurationEnum::ONE_WEEK,
                ContactRestrictionFormType::CHOICE_ONE_MONTH => ContactRestrictionDurationEnum::ONE_MONTH,
                default => null,
            };

            $this->contactRestrictionService->restrict($user, $duration, $permanent);
            $this->addFlash('success', $this->trans('admin.user.flash.contact_restricted'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/restrict_contact.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function liftContactRestriction(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'user_admin_lift_contact_restriction_' . $user->id);

            $this->contactRestrictionService->liftRestriction($user);
            $this->addFlash('success', $this->trans('admin.user.flash.contact_restriction_lifted'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/lift_contact_restriction.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function blockAccount(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'user_admin_block_account_' . $user->id);

            $this->userAccountBlockService->block($user);
            $this->addFlash('success', $this->trans('admin.user.flash.account_blocked'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/block_account.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function unblockAccount(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'user_admin_unblock_account_' . $user->id);

            $this->userAccountBlockService->unblock($user);
            $this->addFlash('success', $this->trans('admin.user.flash.account_unblocked'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/unblock_account.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function forceLogout(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'user_admin_force_logout_' . $user->id);

            $this->sessionInvalidationService->invalidateAllSessions($user);
            $this->addFlash('success', $this->trans('admin.user.flash.logged_out'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/force_logout.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function resendResetEmail(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'user_admin_resend_reset_email_' . $user->id);

            $this->resetPasswordService->resendForAdmin($user, $user->locale);
            $this->addFlash('success', $this->trans('admin.user.flash.reset_email_sent'));

            return $this->redirect($this->detailUrl($user));
        }

        return $this->render('admin/user/resend_reset_email.html.twig', [
            'user' => $user,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    public function forceDelete(AdminContext $context, Request $request): Response
    {
        $user = $this->getUserEntity($context);

        /** @var User $admin */
        $admin = $this->getUser();

        if ($user->email === $admin->email) {
            $this->addFlash('danger', $this->trans('admin.user.flash.cannot_delete_self'));

            return $this->redirect($this->detailUrl($user));
        }

        $form = $this->createForm(UserForceDeleteFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $password */
            $password = $form->get('password')->getData();

            if (! $this->userPasswordHasher->isPasswordValid($admin, $password)) {
                $form->get('password')->addError(new FormError($this->trans('admin.user.error.wrong_password')));
            } else {
                $this->accountDeletionService->deleteImmediately($user);
                $this->addFlash('success', $this->trans('admin.user.flash.deleted'));

                return $this->redirect($this->indexUrl());
            }
        }

        return $this->render('admin/user/force_delete.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * @param AdminContext<User> $context
     */
    private function getUserEntity(AdminContext $context): User
    {
        /** @var User|null $user */
        $user = $context->getEntity()->getInstance();

        if (! $user instanceof User) {
            throw $this->createNotFoundException('User not found.');
        }

        $this->denyAccessUnlessNotAdmin($user);

        return $user;
    }

    private function denyAccessUnlessNotAdmin(User $user): void
    {
        if (\in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw $this->createNotFoundException('User not found.');
        }
    }

    private function detailUrl(User $user): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($user->id)
            ->generateUrl()
        ;
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;
    }

    private function denyUnlessValidCsrfToken(Request $request, string $tokenId): void
    {
        /** @var string $token */
        $token = $request->request->get('_token', '');

        if (! $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $token))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function trans(string $key, array $params = []): string
    {
        return $this->translator->trans($key, $params, 'admin', 'fr');
    }

    /**
     * `User::$locale` est une chaîne brute (pas un enum typé) — les valeurs de choix doivent donc
     * être les `->value` de l'enum, pas ses instances, contrairement à `enumChoices()`.
     *
     * @return array<string, string>
     */
    private function localeChoices(): array
    {
        $choices = [];

        foreach (LocaleAllowedEnum::cases() as $case) {
            $choices[$case->name] = $case->value;
        }

        return $choices;
    }

    /**
     * @param list<\BackedEnum> $cases
     * @return array<string, \BackedEnum>
     */
    private function enumChoices(array $cases): array
    {
        $choices = [];

        foreach ($cases as $case) {
            $choices[$case->name] = $case;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function emailChoices(): array
    {
        $choices = [];

        foreach ($this->userRepository->findDistinctEmails() as $email) {
            $choices[$email] = $email;
        }

        return $choices;
    }

    /**
     * @return array<string, string>
     */
    private function nicknameChoices(): array
    {
        $choices = [];

        foreach ($this->userRepository->findDistinctNicknames() as $nickname) {
            $choices[$nickname] = $nickname;
        }

        return $choices;
    }
}
