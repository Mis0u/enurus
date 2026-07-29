<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Constraint\ImageConstraints;
use App\Entity\ContactThread;
use App\Form\ContactReplyFormType;
use App\Form\ContactVoteFormType;
use App\Security\Voter\ContactThreadVoter;
use App\Service\Contact\ContactThreadReadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ContactThreadShowController extends AbstractController
{
    public function __construct(
        private readonly ContactThreadReadService $contactThreadReadService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/messages/{id}',
            'fr' => '/messagerie/{id}',
            'it' => '/messaggi/{id}',
            'es' => '/mensajes/{id}',
            'pt' => '/mensagens/{id}',
            'de' => '/nachrichten/{id}',
            'nl' => '/berichten/{id}',
            'pl' => '/wiadomosci/{id}',
        ],
        name: 'app_contact_thread_show',
        methods: [Request::METHOD_GET],
    )]
    #[IsGranted(ContactThreadVoter::VIEW, subject: 'thread')]
    public function __invoke(ContactThread $thread): Response
    {
        $this->contactThreadReadService->markAdminMessagesAsRead($thread);

        $canReply = $this->isGranted(ContactThreadVoter::REPLY, $thread);
        $canVote = $this->isGranted(ContactThreadVoter::VOTE, $thread);

        return $this->render('messagerie/show.html.twig', [
            'thread' => $thread,
            'canReply' => $canReply,
            'replyForm' => $canReply ? $this->createForm(ContactReplyFormType::class) : null,
            'canVote' => $canVote,
            'voteForm' => $canVote && null !== $thread->broadcast
                ? $this->createForm(ContactVoteFormType::class, options: [
                    'broadcast' => $thread->broadcast,
                    'locale' => $thread->owner->locale,
                ])
                : null,
            'imageMaxSizeBytes' => ImageConstraints::MAX_SIZE_BYTES,
            'imageAllowedMimeTypes' => ImageConstraints::ALLOWED_MIME_TYPES,
        ]);
    }
}
