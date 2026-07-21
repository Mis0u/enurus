<?php

declare(strict_types=1);

namespace App\Controller\Contact;

use App\Entity\ContactThread;
use App\Entity\User;
use App\Repository\ContactThreadMessageRepository;
use App\Repository\ContactThreadRepository;
use Knp\Component\Pager\Pagination\PaginationInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ContactThreadListController extends AbstractController
{
    private const int DISPLAY_LIMIT_BY_DEFAULT = 10;

    private const array DISPLAY_LIMIT_ALLOWED = [10, 25, 50];

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
    public function __invoke(Request $request, PaginatorInterface $paginator): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $limit = $request->query->getInt('limit', self::DISPLAY_LIMIT_BY_DEFAULT);
        $limit = in_array($limit, self::DISPLAY_LIMIT_ALLOWED, true) ? $limit : self::DISPLAY_LIMIT_BY_DEFAULT;

        /** @var PaginationInterface<int, ContactThread> $pagination */
        $pagination = $paginator->paginate(
            $this->contactThreadRepository->findByOwnerOrderedByActivity($user),
            $request->query->getInt('page', 1),
            $limit
        );

        return $this->render('messagerie/list.html.twig', [
            'pagination' => $pagination,
            'unreadCounts' => $this->contactThreadMessageRepository->countUnreadPerThreadForUser($user),
            'limitAllowed' => self::DISPLAY_LIMIT_ALLOWED,
        ]);
    }
}
