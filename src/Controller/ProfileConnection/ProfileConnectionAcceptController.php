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
    'fr' => '/connexions/{id}/accepter',
    'en' => '/connections/{id}/accept',
    'it' => '/connessioni/{id}/accetta',
    'es' => '/conexiones/{id}/aceptar',
    'pt' => '/conexoes/{id}/aceitar',
    'de' => '/verbindungen/{id}/annehmen',
    'nl' => '/verbindingen/{id}/accepteren',
    'pl' => '/polaczenia/{id}/akceptuj',
], name: 'app_profile_connection_accept', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionAcceptController extends AbstractController
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
        $this->denyUnlessValidFormCsrfToken($request, 'profile_connection_accept');

        try {
            $this->responseService->accept($connection);
            $this->addFlash('success', $this->translator->trans('profile_connection.flash.accepted', [
                'nickname' => $connection->requester->nickname,
            ], 'navigation'));
        } catch (ProfileConnectionException $exception) {
            $this->addFlash('error', $this->messageResolver->forFailure($exception));
        }

        return $this->redirectToRoute('app_profile_connection_list');
    }
}
