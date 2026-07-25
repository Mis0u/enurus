<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Entity\ContactPollOption;
use App\Entity\ContactThread;
use App\Exception\Contact\AlreadyVotedException;
use App\Form\ContactVoteFormType;
use App\Security\Voter\ContactThreadVoter;
use App\Service\Contact\ContactPollVoteService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ContactPollVoteController extends AbstractController
{
    public function __construct(
        private readonly ContactPollVoteService $contactPollVoteService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/messages/{id}/vote',
            'fr' => '/messagerie/{id}/voter',
            'it' => '/messaggi/{id}/vota',
            'es' => '/mensajes/{id}/votar',
            'pt' => '/mensagens/{id}/votar',
            'de' => '/nachrichten/{id}/abstimmen',
            'nl' => '/berichten/{id}/stemmen',
            'pl' => '/wiadomosci/{id}/glosuj',
        ],
        name: 'app_contact_thread_vote',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(ContactThreadVoter::VOTE, subject: 'thread')]
    public function __invoke(ContactThread $thread, Request $request): Response
    {
        if (null === $thread->broadcast) {
            throw $this->createNotFoundException('Vote thread without a broadcast.');
        }

        $form = $this->createForm(ContactVoteFormType::class, options: [
            'broadcast' => $thread->broadcast,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var ContactPollOption $option */
            $option = $form->get('option')->getData();

            try {
                $this->contactPollVoteService->vote($thread, $option);
            } catch (AlreadyVotedException) {
                // Course entre deux requêtes simultanées — le fil affiche de toute façon l'état
                // "déjà voté" (thread.pollVote) au rechargement, rien de plus à faire ici.
            }
        }

        return $this->redirectToRoute('app_contact_thread_show', [
            'id' => $thread->id,
        ]);
    }
}
