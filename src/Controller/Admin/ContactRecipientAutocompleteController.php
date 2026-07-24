<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Autocomplete JS du destinataire pour la messagerie 1-to-1 admin
 * (ContactThreadCrudController::compose()) — jamais utilisé pour les diffusions.
 */
#[IsGranted('ROLE_ADMIN')]
final class ContactRecipientAutocompleteController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/admin/contact-recipients/search', name: 'admin_contact_thread_recipient_search', methods: [Request::METHOD_GET])]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $admin */
        $admin = $this->getUser();

        if (null === $admin->id) {
            throw new \LogicException('Cannot search recipients without a persisted admin id.');
        }

        $query = trim((string) $request->query->get('query', ''));

        if ('' === $query) {
            return new JsonResponse([]);
        }

        $users = $this->userRepository->searchForRecipientAutocomplete($query, $admin->id);

        return new JsonResponse(array_map(static fn (User $user): array => [
            'id' => $user->id?->toRfc4122(),
            'email' => $user->email,
            'locale' => $user->locale,
        ], $users));
    }
}
