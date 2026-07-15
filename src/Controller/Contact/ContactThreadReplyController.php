<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Constraint\ImageConstraints;
use App\Entity\ContactThread;
use App\Entity\User;
use App\Form\ContactReplyFormType;
use App\Security\Voter\ContactThreadVoter;
use App\Service\Contact\ContactThreadReplyService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapUploadedFile;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\File;

#[IsGranted('ROLE_USER')]
final class ContactThreadReplyController extends AbstractController
{
    public function __construct(
        private readonly ContactThreadReplyService $contactThreadReplyService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/messages/{id}/reply',
            'fr' => '/messagerie/{id}/repondre',
            'it' => '/messaggi/{id}/rispondi',
            'es' => '/mensajes/{id}/responder',
            'pt' => '/mensagens/{id}/responder',
            'de' => '/nachrichten/{id}/antworten',
            'nl' => '/berichten/{id}/beantwoorden',
            'pl' => '/wiadomosci/{id}/odpowiedz',
        ],
        name: 'app_contact_thread_reply',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(ContactThreadVoter::REPLY, subject: 'thread')]
    public function __invoke(
        ContactThread $thread,
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
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(ContactReplyFormType::class);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            return $this->render('messagerie/show.html.twig', [
                'thread' => $thread,
                'canReply' => true,
                'replyForm' => $form,
                'imageMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
                'imageAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
            ]);
        }

        /** @var string $message */
        $message = $form->get('message')->getData();

        $this->contactThreadReplyService->reply($user, $thread, $message, $image, fromAdmin: false);

        return $this->redirectToRoute('app_contact_thread_show', [
            'id' => $thread->id,
        ]);
    }
}
