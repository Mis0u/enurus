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

#[Route(path: [
    'fr' => '/connexions/{id}/refuser',
    'en' => '/connections/{id}/decline',
    'it' => '/connessioni/{id}/rifiuta',
    'es' => '/conexiones/{id}/rechazar',
    'pt' => '/conexoes/{id}/recusar',
    'de' => '/verbindungen/{id}/ablehnen',
    'nl' => '/verbindingen/{id}/weigeren',
    'pl' => '/polaczenia/{id}/odrzuc',
], name: 'app_profile_connection_decline', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionDeclineController extends AbstractController
{
    use ValidatesFormCsrfTokenTrait;

    public function __construct(
        private readonly ProfileConnectionResponseService $responseService,
        private readonly ProfileConnectionFailureMessageResolver $messageResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[IsGranted(ProfileConnectionVoter::RESPOND, subject: 'connection')]
    public function __invoke(Request $request, ProfileConnection $connection): Response
    {
        $this->denyUnlessValidFormCsrfToken($request, 'profile_connection_decline');

        try {
            $this->responseService->decline($connection);
            $this->addFlash('success', $this->translator->trans('profile_connection.flash.declined', [
                'nickname' => $connection->requester->nickname,
            ], 'navigation'));
        } catch (ProfileConnectionException $exception) {
            $this->addFlash('error', $this->messageResolver->forFailure($exception));
        }

        return $this->redirectToRoute('app_profile_connection_list');
    }
}
