<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Constraint\ImageConstraints;
use App\Entity\ContactBroadcast;
use App\Entity\User;
use App\Enum\Contact\ContactBroadcastTargetEnum;
use App\Enum\Translations\LocaleAllowedEnum;
use App\Form\Admin\ContactBroadcastComposeFormType;
use App\Service\Contact\ContactThreadComposeService;
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
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

/**
 * @extends AbstractCrudController<ContactBroadcast>
 */
#[IsGranted('ROLE_ADMIN')]
final class ContactBroadcastCrudController extends AbstractCrudController
{
    private const string ACTION_COMPOSE = 'compose';

    public function __construct(
        private readonly ContactThreadComposeService $contactThreadComposeService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly ImageUploadService $imageUploadService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ContactBroadcast::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Diffusion')
            ->setEntityLabelInPlural('Diffusions')
            ->setDefaultSort([
                'sentAt' => 'DESC',
            ])
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('subject', 'Sujet');
        yield Field::new('targetDescription', 'Destinataires')
            ->setVirtual(true)
            ->formatValue(fn (mixed $value, ContactBroadcast $broadcast): string => $this->describeTarget($broadcast))
            ->setTemplatePath('admin/contact_broadcast/_target.html.twig')
        ;
        yield IntegerField::new('recipientCount', 'Nb. destinataires');
        yield TextField::new('sentBy.email', 'Envoyé par');
        yield DateTimeField::new('sentAt', 'Envoyé le');
        yield Field::new('body', 'Message')
            ->onlyOnDetail()
            ->setTemplatePath('admin/contact_broadcast/_body.html.twig')
        ;
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
                Action::new(self::ACTION_COMPOSE, 'Nouvelle diffusion')
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
            $count = $this->handleCompose($form, $admin, $image);
            $this->addFlash('success', \sprintf('Diffusion en cours d\'envoi à %d destinataire(s).', $count));

            return $this->redirect($this->indexUrl());
        }

        return $this->render('admin/contact_broadcast/compose.html.twig', [
            'form' => $form,
        ]);
    }

    private function describeTarget(ContactBroadcast $broadcast): string
    {
        if (ContactBroadcastTargetEnum::LOCALE === $broadcast->target && null !== $broadcast->locale) {
            return \sprintf('Tous les utilisateurs (%s)', strtoupper($broadcast->locale->value));
        }

        return 'Tous les utilisateurs';
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

        $locale = ContactBroadcastComposeFormType::TARGET_ALL === $targetChoice
            ? null
            : LocaleAllowedEnum::from($targetChoice);

        if (null === $admin->id) {
            throw new \LogicException('Cannot compose a broadcast without a persisted admin id.');
        }

        $target = null === $locale ? ContactBroadcastTargetEnum::ALL : ContactBroadcastTargetEnum::LOCALE;

        return $this->contactThreadComposeService->composeToAudience($admin, $target, $locale, $subject, $body, $image);
    }

    private function indexUrl(): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl()
        ;
    }
}
