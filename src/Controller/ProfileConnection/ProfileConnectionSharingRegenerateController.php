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
final class ProfileConnectionSharingRegenerateController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ShareCodeAssigner $shareCodeAssigner,
    ) {
    }

    #[Route(
        path: [
            'en' => '/connections/sharing/regenerate',
            'fr' => '/connexions/partage/regenerer',
            'it' => '/connessioni/condivisione/rigenera',
            'es' => '/conexiones/compartir/regenerar',
            'pt' => '/conexoes/partilha/gerar-novo',
            'de' => '/verbindungen/teilen/neu-generieren',
            'nl' => '/verbindingen/delen/opnieuw-genereren',
            'pl' => '/polaczenia/udostepnianie/wygeneruj-nowy',
        ],
        name: 'app_profile_connection_sharing_regenerate',
        methods: [Request::METHOD_POST],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{_token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('profile_connection_sharing_regenerate', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        /** @var User $user */
        $user = $this->getUser();

        if (! $user->isDiscoverable) {
            return $this->json([
                'error' => 'Profile sharing is not enabled',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->shareCodeAssigner->assign($user);
        $this->em->flush();

        return $this->json([
            'isDiscoverable' => $user->isDiscoverable,
            'shareCode' => $user->shareCode,
        ]);
    }
}
