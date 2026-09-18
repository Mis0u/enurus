<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Controller\Trait\ValidatesFormCsrfTokenTrait;
use App\Entity\ProfileConnection;
use App\Entity\User;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Security\Voter\ProfileConnectionVoter;
use App\Service\ProfileSharing\ProfileConnectionFailureMessageResolver;
use App\Service\ProfileSharing\ProfileConnectionResponseService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Mettre fin à une connexion acceptée : ouvert aux deux parties, dans les deux sens.
 */
#[Route(path: [
    'fr' => '/connexions/{id}/retirer',
    'en' => '/connections/{id}/revoke',
    'it' => '/connessioni/{id}/revoca',
    'es' => '/conexiones/{id}/revocar',
    'pt' => '/conexoes/{id}/revogar',
    'de' => '/verbindungen/{id}/widerrufen',
    'nl' => '/verbindingen/{id}/intrekken',
    'pl' => '/polaczenia/{id}/cofnij',
], name: 'app_profile_connection_revoke', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionRevokeController extends AbstractController
{
    use ValidatesFormCsrfTokenTrait;

    public function __construct(
        private readonly ProfileConnectionResponseService $responseService,
        private readonly ProfileConnectionFailureMessageResolver $messageResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::REVOKE, subject: 'connection')]
    public function __invoke(Request $request, ProfileConnection $connection): Response
    {
        $this->denyUnlessValidFormCsrfToken($request, 'profile_connection_revoke');

        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        try {
            $this->responseService->revoke($connection);
            $this->addFlash('success', $this->translator->trans('profile_connection.flash.revoked', [
                'nickname' => $connection->counterpartOf($user)->nickname,
            ], 'navigation'));
        } catch (ProfileConnectionException $exception) {
            $this->addFlash('error', $this->messageResolver->forFailure($exception));
        }

        return $this->redirectToRoute('app_profile_connection_list');
    }
}
