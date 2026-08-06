<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Constraint\ImageConstraints;
use App\Entity\ContactBroadcast;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Contact\ContactCategoryEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Form\Admin\ContactBroadcastComposeFormType;
use App\Service\Contact\ContactThreadComposeService;
use App\Service\Dashboard\Admin\ContactPollStatsService;
use App\Service\Utils\ImageUploadService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @extends AbstractCrudController<ContactBroadcast>
 */
#[IsGranted('ROLE_ADMIN')]
final class ContactBroadcastCrudController extends AbstractCrudController
{
    private const string ACTION_COMPOSE = 'compose';

    private const int MIN_POLL_OPTIONS = 2;

    public function __construct(
        private readonly ContactThreadComposeService $contactThreadComposeService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ImageUploadService $imageUploadService,
        private readonly TranslatorInterface $translator,
        private readonly ContactPollStatsService $contactPollStatsService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactBroadcast::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular($this->trans('admin.broadcast.label.singular'))
            ->setEntityLabelInPlural($this->trans('admin.broadcast.label.plural'))
            ->setDefaultSort([
                'sentAt' => 'DESC',
            ])
            ->setTimezone('Europe/Paris')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('subject', $this->trans('admin.broadcast.compose.subject_label'));
        yield Field::new('categoryDescription', $this->trans('admin.broadcast.field.category'))
            ->setVirtual(true)
            ->formatValue(fn (mixed $value, ContactBroadcast $broadcast): string => $this->describeCategory($broadcast))
            ->setTemplatePath('admin/contact_broadcast/_category.html.twig')
        ;
        yield Field::new('targetDescription', $this->trans('admin.broadcast.field.target'))
            ->setVirtual(true)
            ->formatValue(fn (mixed $value, ContactBroadcast $broadcast): string => $this->describeTarget($broadcast))
            ->setTemplatePath('admin/contact_broadcast/_target.html.twig')
        ;
        yield IntegerField::new('recipientCount', $this->trans('admin.broadcast.field.recipient_count'));
        yield TextField::new('sentBy.email', $this->trans('admin.broadcast.field.sent_by'));
        yield DateTimeField::new('sentAt', $this->trans('admin.broadcast.field.sent_at'));
        yield DateTimeField::new('pollClosesAt', $this->trans('admin.broadcast.poll.closes_at'))
            ->onlyOnDetail()
        ;
        yield Field::new('body', $this->trans('admin.broadcast.field.body'))
            ->onlyOnDetail()
            ->setTemplatePath('admin/contact_broadcast/_body.html.twig')
        ;
        if (Crud::PAGE_DETAIL === $pageName && $this->getDetailBroadcast()?->isPoll()) {
            yield Field::new('pollResults', $this->trans('admin.broadcast.poll.results_title'))
                ->onlyOnDetail()
                ->setVirtual(true)
                ->formatValue(fn (mixed $value, ContactBroadcast $broadcast): mixed => $this->contactPollStatsService->getData($broadcast))
                ->setTemplatePath('admin/contact_broadcast/_poll_results.html.twig')
            ;
        }
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $imagePaths = [];
        foreach ($entityInstance->threads as $thread) {
            foreach ($thread->messages as $message) {
                $imagePaths[] = $message->imagePath;
            }
        }

        parent::deleteEntity($entityManager, $entityInstance);

        foreach ($imagePaths as $imagePath) {
            $this->imageUploadService->delete($imagePath);
        }
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW, Action::EDIT)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(
                Crud::PAGE_INDEX,
                Action::new(self::ACTION_COMPOSE, $this->trans('admin.broadcast.action.compose'))
                    ->linkToCrudAction(self::ACTION_COMPOSE)
                    ->createAsGlobalAction()
            )
        ;
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

        $form = $this->createForm(ContactBroadcastComposeFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $pollErrorKey = $this->validatePollFields($form);

            if (null === $pollErrorKey) {
                $count = $this->handleCompose($form, $admin, $image);
                $this->addFlash('success', $this->translator->trans('admin.broadcast.flash.sending', [
                    'count' => $count,
                ], 'admin', 'fr'));

                return $this->redirect($this->indexUrl());
            }

            $form->get('pollOptions')->addError(new FormError($this->trans($pollErrorKey)));
        }

        return $this->render('admin/contact_broadcast/compose.html.twig', [
            'form' => $form,
        ]);
    }

    /**
     * Uniquement défini sur la page de détail — pas d'accès à l'entité courante ailleurs
     * (index/compose n'ont pas de ContactBroadcast résolu dans le contexte).
     */
    private function getDetailBroadcast(): ?ContactBroadcast
    {
        $broadcast = $this->getContext()?->getEntity()->getInstance();

        return $broadcast instanceof ContactBroadcast ? $broadcast : null;
    }

    private function describeCategory(ContactBroadcast $broadcast): string
    {
        return $broadcast->isPoll()
            ? $this->trans('admin.broadcast.category.vote')
            : $this->trans('admin.broadcast.category.informative');
    }

    private function describeTarget(ContactBroadcast $broadcast): string
    {
        if (ContactBroadcastTargetEnum::LOCALE === $broadcast->target && null !== $broadcast->locale) {
            return $this->translator->trans('admin.broadcast.target.locale', [
                'locale' => strtoupper($broadcast->locale->value),
            ], 'admin', 'fr');
        }

        return $this->trans('admin.broadcast.target.all');
    }

    /**
     * @param FormInterface<null> $form
     */
    private function handleCompose(FormInterface $form, User $admin, ?UploadedFile $image): int
    {
        /** @var string $targetChoice */
        $targetChoice = $form->get('target')->getData();
        /** @var string $subject */
        $subject = $form->get('subject')->getData();
        /** @var string $body */
        $body = $form->get('body')->getData();
        /** @var string $categoryChoice */
        $categoryChoice = $form->get('category')->getData();

        $locale = ContactBroadcastComposeFormType::TARGET_ALL === $targetChoice
            ? null
            : LocaleAllowedEnum::from($targetChoice);

        if (null === $admin->id) {
            throw new \LogicException('Cannot compose a broadcast without a persisted admin id.');
        }

        $target = null === $locale ? ContactBroadcastTargetEnum::ALL : ContactBroadcastTargetEnum::LOCALE;
        $category = ContactCategoryEnum::from($categoryChoice);

        return $this->contactThreadComposeService->composeToAudience(
            $admin,
            $category,
            $target,
            $locale,
            $subject,
            $body,
            $image,
            $this->decodePollOptionLabels($form),
            $this->getPollDurationDays($form),
        );
    }

    /**
     * @param FormInterface<null> $form
     */
    private function validatePollFields(FormInterface $form): ?string
    {
        /** @var string $categoryChoice */
        $categoryChoice = $form->get('category')->getData();

        if (ContactCategoryEnum::VOTE->value !== $categoryChoice) {
            return null;
        }

        if (self::MIN_POLL_OPTIONS > \count($this->decodePollOptionLabels($form))) {
            return 'admin.broadcast.error.poll_options_required';
        }

        $duration = $this->getPollDurationDays($form);

        if (null === $duration) {
            return 'admin.broadcast.error.poll_duration_required';
        }

        return null;
    }

    /**
     * @param FormInterface<null> $form
     */
    private function getPollDurationDays(FormInterface $form): ?int
    {
        /** @var ?int $duration */
        $duration = $form->get('pollDurationDays')->getData();

        return null !== $duration && 1 <= $duration ? $duration : null;
    }

    /**
     * Décode le JSON construit côté JS (assets/controllers/admin/contact_poll_options_controller.js)
     * — jamais de valeur inattendue ne remonte plus loin qu'un tableau vide, laissé à
     * validatePollFields() plutôt qu'une exception (entrée admin, mais toujours une frontière
     * externe : un contournement du JS ne doit pas produire un 500).
     *
     * @param FormInterface<null> $form
     * @return list<string>
     */
    private function decodePollOptionLabels(FormInterface $form): array
    {
        /** @var string $raw */
        $raw = $form->get('pollOptions')->getData() ?? '';

        if ('' === $raw) {
            return [];
        }

        try {
            $decoded = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        if (! is_array($decoded)) {
            return [];
        }

        $labels = [];
        foreach ($decoded as $label) {
            if (is_string($label) && '' !== trim($label)) {
                $labels[] = trim($label);
            }
        }

        return $labels;
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;
    }

    private function trans(string $key): string
    {
        return $this->translator->trans($key, [], 'admin', 'fr');
    }
}
