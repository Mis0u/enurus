<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Entity\User;
use App\Repository\ContactThreadMessageRepository;
use App\Repository\ContactThreadRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ContactThreadListController extends AbstractController
{
    public function __construct(
        private readonly ContactThreadRepository $contactThreadRepository,
        private readonly ContactThreadMessageRepository $contactThreadMessageRepository,
    ) {
    }

    #[Route(
        path: [
            'en' => '/messages',
            'fr' => '/messagerie',
            'it' => '/messaggi',
            'es' => '/mensajes',
            'pt' => '/mensagens',
            'de' => '/nachrichten',
            'nl' => '/berichten',
            'pl' => '/wiadomosci',
        ],
        name: 'app_contact_thread_list',
        methods: [Request::METHOD_GET],
    )]
    public function __invoke(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('messagerie/list.html.twig', [
            'threads' => $this->contactThreadRepository->findByOwnerOrderedByActivity($user),
            'unreadCounts' => $this->contactThreadMessageRepository->countUnreadPerThreadForUser($user),
        ]);
    }
}
