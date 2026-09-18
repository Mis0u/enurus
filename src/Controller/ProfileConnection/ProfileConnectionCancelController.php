<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Controller\Trait\ValidatesFormCsrfTokenTrait;
use App\Entity\ProfileConnection;
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
 * Retrait par le demandeur d'une demande encore sans réponse.
 */
#[Route(path: [
    'fr' => '/connexions/{id}/annuler',
    'en' => '/connections/{id}/cancel',
    'it' => '/connessioni/{id}/annulla',
    'es' => '/conexiones/{id}/cancelar',
    'pt' => '/conexoes/{id}/cancelar',
    'de' => '/verbindungen/{id}/abbrechen',
    'nl' => '/verbindingen/{id}/annuleren',
    'pl' => '/polaczenia/{id}/anuluj',
], name: 'app_profile_connection_cancel', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionCancelController extends AbstractController
{
    use ValidatesFormCsrfTokenTrait;

    public function __construct(
        private readonly ProfileConnectionResponseService $responseService,
        private readonly ProfileConnectionFailureMessageResolver $messageResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::CANCEL, subject: 'connection')]
    public function __invoke(Request $request, ProfileConnection $connection): Response
    {
        $this->denyUnlessValidFormCsrfToken($request, 'profile_connection_cancel');

        try {
            $this->responseService->cancel($connection);
            $this->addFlash('success', $this->translator->trans('profile_connection.flash.cancelled', [
                'nickname' => $connection->addressee->nickname,
            ], 'navigation'));
        } catch (ProfileConnectionException $exception) {
            $this->addFlash('error', $this->messageResolver->forFailure($exception));
        }

        return $this->redirectToRoute('app_profile_connection_list');
    }
}
