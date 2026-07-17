<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\ContactThread;
use App\Security\Voter\ContactThreadVoter;
use App\Service\Contact\ContactThreadHideService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class ContactThreadDeleteController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public function __construct(
        private readonly ContactThreadHideService $contactThreadHideService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: [
            'en' => '/messages/{id}/delete',
            'fr' => '/messagerie/{id}/supprimer',
            'it' => '/messaggi/{id}/elimina',
            'es' => '/mensajes/{id}/eliminar',
            'pt' => '/mensagens/{id}/eliminar',
            'de' => '/nachrichten/{id}/loeschen',
            'nl' => '/berichten/{id}/verwijderen',
            'pl' => '/wiadomosci/{id}/usun',
        ],
        name: 'app_contact_thread_delete',
        methods: [Request::METHOD_DELETE],
    )]
    #[IsGranted(ContactThreadVoter::DELETE, subject: 'thread')]
    public function __invoke(ContactThread $thread, Request $request): JsonResponse
    {
        if ($response = $this->denyUnlessXmlHttpRequest($request)) {
            return $response;
        }

        if (null === $thread->id) {
            throw new \LogicException('Cannot delete a contact thread without a persisted id.');
        }

        $this->denyUnlessValidCsrfToken($request, 'contact_thread_delete_' . $thread->id->toRfc4122());

        $this->contactThreadHideService->hideForUser($thread);

        return $this->json([
            'success' => true,
            'message' => $this->translator->trans('messagerie.delete.success', [], 'navigation'),
        ]);
    }
}
