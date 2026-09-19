<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\User;
use App\Service\ProfileSharing\ShareCodeAssigner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class ProfileConnectionSharingToggleController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShareCodeAssigner $shareCodeAssigner,
    ) {
    }

    #[Route(
        path: [
            'en' => '/connections/sharing',
            'fr' => '/connexions/partage',
            'it' => '/connessioni/condivisione',
            'es' => '/conexiones/compartir',
            'pt' => '/conexoes/partilha',
            'de' => '/verbindungen/teilen',
            'nl' => '/verbindingen/delen',
            'pl' => '/polaczenia/udostepnianie',
        ],
        name: 'app_profile_connection_sharing_toggle',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{isDiscoverable?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('profile_connection_sharing_toggle', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();
        $isDiscoverable = (bool) ($payload['isDiscoverable'] ?? false);

        $user->isDiscoverable = $isDiscoverable;

        if ($isDiscoverable && null === $user->shareCode) {
            $this->shareCodeAssigner->assign($user);
        }

        $this->em->flush();

        return $this->json([
            'isDiscoverable' => $user->isDiscoverable,
            'shareCode' => $user->shareCode,
        ]);
    }
}
