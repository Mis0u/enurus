<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Entity\ContactThread;
use App\Security\Voter\ContactThreadVoter;
use App\Service\Contact\ContactThreadCloseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Pas d'interface admin pour l'instant — cette action est destinée à être branchée sur un futur
 * panneau EasyAdmin. Elle est fonctionnelle et sécurisée dès maintenant (testable via fixtures et
 * appel direct en ROLE_ADMIN), simplement sans déclencheur dans l'UI actuelle.
 */
#[IsGranted('ROLE_ADMIN')]
final class ContactThreadCloseController extends AbstractController
{
    public function __construct(
        private readonly ContactThreadCloseService $contactThreadCloseService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/messages/{id}/close',
            'fr' => '/messagerie/{id}/cloturer',
            'it' => '/messaggi/{id}/chiudi',
            'es' => '/mensajes/{id}/cerrar',
            'pt' => '/mensagens/{id}/fechar',
            'de' => '/nachrichten/{id}/schliessen',
            'nl' => '/berichten/{id}/sluiten',
            'pl' => '/wiadomosci/{id}/zamknij',
        ],
        name: 'app_contact_thread_close',
        methods: [Request::METHOD_POST],
    )]
    #[IsGranted(ContactThreadVoter::CLOSE, subject: 'thread')]
    public function __invoke(ContactThread $thread, Request $request): Response
    {
        $token = $request->request->get('_token');

        if (! $this->isCsrfTokenValid('contact_thread_close', \is_string($token) ? $token : null)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $this->contactThreadCloseService->close($thread);

        return $this->redirectToRoute('app_contact_thread_show', [
            'id' => $thread->id,
        ]);
    }
}
