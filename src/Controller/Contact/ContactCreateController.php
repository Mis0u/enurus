<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Constraint\ImageConstraints;
use App\Entity\User;
use App\Enum\Contact\ContactCategoryEnum;
use App\Form\ContactFormType;
use App\Service\Contact\ContactThreadService;
use App\Service\Security\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class ContactCreateController extends AbstractController
{
    public function __construct(
        private readonly ContactThreadService $contactThreadService,
        private readonly RateLimiterService $rateLimiterService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: [
            'en' => '/contact',
            'fr' => '/contact',
            'it' => '/contatti',
            'es' => '/contacto',
            'pt' => '/contacto',
            'de' => '/kontakt',
            'nl' => '/contact',
            'pl' => '/kontakt',
        ],
        name: 'app_contact_create',
        methods: [Request::METHOD_POST],
    )]
    public function __invoke(
        Request $request,
        RateLimiterFactory $contactLimiter,
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
        /** @var User $user */
        $user = $this->getUser();

        if ($user->isContactRestricted) {
            return $this->redirectToRoute('app_contact');
        }

        $form = $this->createForm(ContactFormType::class);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            return $this->render('contact/show.html.twig', [
                'restricted' => false,
                'contactForm' => $form,
                'imageMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
                'imageAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
            ]);
        }

        return $this->handleSubmission($form, $user, $contactLimiter, $image);
    }

    /**
     * @param FormInterface<null> $form
     */
    private function handleSubmission(
        FormInterface $form,
        User $user,
        RateLimiterFactory $contactLimiter,
        ?UploadedFile $image,
    ): Response {
        if (null === $user->id) {
            throw new \LogicException('Cannot rate-limit a contact thread for a user without a persisted id.');
        }

        $limitResult = $this->rateLimiterService->checkLimit($contactLimiter, $user->id->toRfc4122());

        if (! $limitResult['accepted']) {
            $this->addFlash(
                'error',
                $this->translator->trans('rate_limiter.too_many_attempt', [
                    'minutes' => $limitResult['minutes'],
                ], 'security'),
            );

            return $this->redirectToRoute('app_contact');
        }

        /** @var ContactCategoryEnum $category */
        $category = $form->get('category')->getData();
        /** @var string $subject */
        $subject = $form->get('subject')->getData();
        /** @var string $message */
        $message = $form->get('message')->getData();

        $this->contactThreadService->create($user, $category, $subject, $message, $image);

        $this->addFlash('success', $this->translator->trans('contact.feedback.success', [], 'navigation'));

        return $this->redirectToRoute('app_contact');
    }
}
