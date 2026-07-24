<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Constraint\ImageConstraints;
use App\Entity\ContactThread;
use App\Entity\ContactThreadMessage;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Contact\ContactRestrictionDurationEnum;
use App\Enum\Contact\ContactThreadStatusEnum;
use App\Form\Admin\ContactRestrictionFormType;
use App\Form\Admin\ContactThreadComposeFormType;
use App\Form\ContactReplyFormType;
use App\Repository\UserRepository;
use App\Security\Voter\ContactThreadVoter;
use App\Service\Contact\ContactRestrictionService;
use App\Service\Contact\ContactThreadCloseService;
use App\Service\Contact\ContactThreadComposeService;
use App\Service\Contact\ContactThreadReplyService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints\File;

/**
 * @extends AbstractCrudController<ContactThread>
 */
#[IsGranted('ROLE_ADMIN')]
final class ContactThreadCrudController extends AbstractCrudController
{
    private const string ACTION_REPLY = 'reply';

    private const string ACTION_CLOSE = 'close';

    private const string ACTION_BLOCK = 'block';

    private const string ACTION_UNBLOCK = 'unblock';

    private const string ACTION_COMPOSE = 'compose';

    public function __construct(
        private readonly ContactThreadReplyService $contactThreadReplyService,
        private readonly ContactThreadCloseService $contactThreadCloseService,
        private readonly ContactRestrictionService $contactRestrictionService,
        private readonly ContactThreadComposeService $contactThreadComposeService,
        private readonly UserRepository $userRepository,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ImageUploadService $imageUploadService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactThread::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Fil de discussion')
            ->setEntityLabelInPlural('Messagerie')
            ->setDefaultSort([
                'updatedAt' => 'DESC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('owner.email', 'Utilisateur');
        yield ChoiceField::new('category')
            ->setChoices($this->enumChoices(ContactCategoryEnum::cases()))
            ->renderAsBadges([
                ContactCategoryEnum::BUG->value => 'danger',
                ContactCategoryEnum::SUGGESTION->value => 'info',
                ContactCategoryEnum::QUESTION->value => 'primary',
                ContactCategoryEnum::LOVE->value => 'warning',
                ContactCategoryEnum::OTHER->value => 'secondary',
                ContactCategoryEnum::INFORMATIVE->value => 'success',
            ])
        ;
        yield TextField::new('subject', 'Sujet')
            ->formatValue(fn (mixed $value, ContactThread $thread): string => $this->formatSubjectForAwaitingReply($thread))
        ;
        yield ChoiceField::new('status')
            ->setChoices($this->enumChoices(ContactThreadStatusEnum::cases()))
            ->renderAsBadges([
                ContactThreadStatusEnum::AWAITING_ADMIN_REPLY->value => 'warning',
                ContactThreadStatusEnum::ANSWERED_BY_ADMIN->value => 'success',
                ContactThreadStatusEnum::CLOSED->value => 'secondary',
            ])
        ;
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
        yield DateTimeField::new('updatedAt', 'Mis à jour le')->hideOnForm();
        yield DateTimeField::new('closedAt', 'Clôturé le')->hideOnIndex();
        yield Field::new('messages', 'Conversation')
            ->onlyOnDetail()
            ->setTemplatePath('admin/contact_thread/_messages.html.twig')
        ;
    }

    /**
     * Un envoi groupé est toujours `INFORMATIVE`, donc jamais répondu (cf. ContactThreadVoter) — les
     * fils qu'il génère n'ont donc jamais besoin d'apparaître ici : ils restent consultables en
     * agrégé via ContactBroadcastCrudController ("Diffusions").
     */
    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.broadcast IS NULL')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(
                ChoiceFilter::new('status')
                    ->setChoices($this->enumChoices(ContactThreadStatusEnum::cases()))
                    ->renderExpanded()
                    ->canSelectMultiple()
            )
            ->add(
                ChoiceFilter::new('category')
                    ->setChoices($this->enumChoices(ContactCategoryEnum::cases()))
                    ->renderExpanded()
                    ->canSelectMultiple()
            )
            ->add(
                EntityFilter::new('owner', 'Utilisateur')
                    ->setFormTypeOption('value_type_options', [
                        'choice_label' => static fn (User $user): string => $user->email,
                    ])
            )
            ->add('updatedAt')
        ;
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $imagePaths = array_map(static fn (ContactThreadMessage $message): ?string => $message->imagePath, $entityInstance->messages->toArray());

        parent::deleteEntity($entityManager, $entityInstance);

        foreach ($imagePaths as $imagePath) {
            $this->imageUploadService->delete($imagePath);
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(
                Crud::PAGE_INDEX,
                Action::new(self::ACTION_COMPOSE, 'Nouveau message')
                    ->linkToCrudAction(self::ACTION_COMPOSE)
                    ->createAsGlobalAction()
            )
            ->add(Crud::PAGE_DETAIL, Action::new(self::ACTION_REPLY, 'Répondre')->linkToCrudAction(self::ACTION_REPLY))
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_CLOSE, 'Clôturer')
                    ->linkToCrudAction(self::ACTION_CLOSE)
                    ->displayIf(static fn (ContactThread $thread): bool => ContactThreadStatusEnum::CLOSED !== $thread->status)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_BLOCK, "Bloquer l'utilisateur")
                    ->linkToCrudAction(self::ACTION_BLOCK)
                    ->displayIf(static fn (ContactThread $thread): bool => ! $thread->owner->isContactRestricted)
            )
            ->add(
                Crud::PAGE_DETAIL,
                Action::new(self::ACTION_UNBLOCK, "Débloquer l'utilisateur")
                    ->linkToCrudAction(self::ACTION_UNBLOCK)
                    ->displayIf(static fn (ContactThread $thread): bool => $thread->owner->isContactRestricted)
            )
        ;

        return $actions;
    }

    /**
     * @param AdminContext<ContactThread> $context
     */
    public function reply(
        AdminContext $context,
        Request $request,
        #[MapUploadedFile(
            constraints: [
                new File(
                    maxSize: ImageConstraints::MAX_SIZE_WEIGHT,
                    mimeTypes: ImageConstraints::ALLOWED_MIME_TYPES,
                    maxSizeMessage: 'contact.image.too_large',
                    mimeTypesMessage: 'contact.image.invalid_type',
                ),
            ]
        )]
        ?UploadedFile $image = null,
    ): Response {
        $thread = $this->getThread($context);
        $this->denyAccessUnlessGranted(ContactThreadVoter::VIEW, $thread);

        /** @var User $admin */
        $admin = $this->getUser();

        $form = $this->createForm(ContactReplyFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $body */
            $body = $form->get('message')->getData();

            $this->contactThreadReplyService->reply($admin, $thread, $body, $image, fromAdmin: true);

            if ($request->request->getBoolean('closeAfterReply')) {
                $this->contactThreadCloseService->close($thread);
                $this->addFlash('success', 'Réponse envoyée et fil clôturé.');
            } else {
                $this->addFlash('success', 'Réponse envoyée.');
            }

            return $this->redirect($this->detailUrl($thread));
        }

        return $this->render('admin/contact_thread/reply.html.twig', [
            'thread' => $thread,
            'form' => $form,
        ]);
    }

    public function compose(
        Request $request,
        #[MapUploadedFile(
            constraints: [
                new File(
                    maxSize: ImageConstraints::MAX_SIZE_WEIGHT,
                    mimeTypes: ImageConstraints::ALLOWED_MIME_TYPES,
                    maxSizeMessage: 'contact.image.too_large',
                    mimeTypesMessage: 'contact.image.invalid_type',
                ),
            ]
        )]
        ?UploadedFile $image = null,
    ): Response {
        /** @var User $admin */
        $admin = $this->getUser();

        $form = $this->createForm(ContactThreadComposeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $this->handleCompose($form, $admin, $image)) {
            $this->addFlash('success', 'Message envoyé.');

            return $this->redirect($this->indexUrl());
        }

        return $this->render('admin/contact_thread/compose.html.twig', [
            'form' => $form,
            'recipientSearchUrl' => $this->generateUrl('admin_contact_thread_recipient_search'),
        ]);
    }

    /**
     * @param AdminContext<ContactThread> $context
     */
    public function close(AdminContext $context, Request $request): Response
    {
        $thread = $this->getThread($context);
        $this->denyAccessUnlessGranted(ContactThreadVoter::CLOSE, $thread);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'contact_thread_admin_close_' . $thread->id);

            $this->contactThreadCloseService->close($thread);
            $this->addFlash('success', 'Fil clôturé.');

            return $this->redirect($this->detailUrl($thread));
        }

        return $this->render('admin/contact_thread/close.html.twig', [
            'thread' => $thread,
        ]);
    }

    /**
     * @param AdminContext<ContactThread> $context
     */
    public function block(AdminContext $context, Request $request): Response
    {
        $thread = $this->getThread($context);

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

            $this->contactRestrictionService->restrict($thread->owner, $duration, $permanent);
            $this->addFlash('success', 'Utilisateur bloqué.');

            return $this->redirect($this->detailUrl($thread));
        }

        return $this->render('admin/contact_thread/block.html.twig', [
            'thread' => $thread,
            'form' => $form,
        ]);
    }

    /**
     * @param AdminContext<ContactThread> $context
     */
    public function unblock(AdminContext $context, Request $request): Response
    {
        $thread = $this->getThread($context);

        if ($request->isMethod('POST')) {
            $this->denyUnlessValidCsrfToken($request, 'contact_thread_admin_unblock_' . $thread->owner->id);

            $this->contactRestrictionService->liftRestriction($thread->owner);
            $this->addFlash('success', 'Utilisateur débloqué.');

            return $this->redirect($this->detailUrl($thread));
        }

        return $this->render('admin/contact_thread/unblock.html.twig', [
            'thread' => $thread,
        ]);
    }

    /**
     * Sujet en gras pour les fils en attente d'une réponse admin — seul indicateur "non lu" côté
     * admin, faute d'un champ dédié (contrairement à `ContactThreadMessage::$readAt`, qui ne suit
     * que la lecture par l'utilisateur des messages admin). `formatValue()` retourne du HTML brut
     * (jamais échappé automatiquement par EasyAdmin), d'où l'échappement manuel du sujet.
     */
    private function formatSubjectForAwaitingReply(ContactThread $thread): string
    {
        $escapedSubject = htmlspecialchars($thread->subject, \ENT_QUOTES);

        return ContactThreadStatusEnum::AWAITING_ADMIN_REPLY === $thread->status
            ? \sprintf('<strong>%s</strong>', $escapedSubject)
            : $escapedSubject;
    }

    /**
     * @param FormInterface<null> $form
     */
    private function handleCompose(FormInterface $form, User $admin, ?UploadedFile $image): bool
    {
        /** @var ContactCategoryEnum $category */
        $category = $form->get('category')->getData();
        /** @var string $subject */
        $subject = $form->get('subject')->getData();
        /** @var string $body */
        $body = $form->get('body')->getData();
        /** @var string $recipientId */
        $recipientId = $form->get('recipientId')->getData();

        $recipient = Uuid::isValid($recipientId) ? $this->userRepository->find(Uuid::fromString($recipientId)) : null;

        if (! $recipient instanceof User || $recipient === $admin) {
            $form->get('recipientId')->addError(new FormError('Aucun utilisateur valide trouvé pour ce destinataire.'));

            return false;
        }

        $this->contactThreadComposeService->composeToSingleUser($admin, $recipient, $category, $subject, $body, $image);

        return true;
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;
    }

    /**
     * @param AdminContext<ContactThread> $context
     */
    private function getThread(AdminContext $context): ContactThread
    {
        /** @var ContactThread|null $thread */
        $thread = $context->getEntity()->getInstance();

        if (! $thread instanceof ContactThread) {
            throw $this->createNotFoundException('Contact thread not found.');
        }

        return $thread;
    }

    private function detailUrl(ContactThread $thread): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::DETAIL)
            ->setEntityId($thread->id)
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
}
