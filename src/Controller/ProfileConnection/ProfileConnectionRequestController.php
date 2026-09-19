<?php

declare(strict_types=1);

namespace App\Controller\ProfileConnection;

use App\Entity\User;
use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use App\Form\ProfileConnectionRequestType;
use App\Service\ProfileSharing\ProfileConnectionFailureMessageResolver;
use App\Service\ProfileSharing\ProfileConnectionRequestService;
use App\Service\ProfileSharing\ProfileLookupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Une seule saisie `Pseudo#CODE` : la recherche et l'envoi de la demande se font en une étape, sans
 * écran intermédiaire — le pseudo devant correspondre au code, la saisie confirme déjà l'identité,
 * et aucun identifiant de compte n'est jamais transmis au navigateur.
 */
#[Route(path: [
    'fr' => '/connexions/demander',
    'en' => '/connections/request',
    'it' => '/connessioni/richiedi',
    'es' => '/conexiones/solicitar',
    'pt' => '/conexoes/solicitar',
    'de' => '/verbindungen/anfragen',
    'nl' => '/verbindingen/aanvragen',
    'pl' => '/polaczenia/popros',
], name: 'app_profile_connection_request', methods: ['POST'])]
#[IsGranted('ROLE_USER')]
final class ProfileConnectionRequestController extends AbstractController
{
    public function __construct(
        private readonly ProfileLookupService $lookupService,
        private readonly ProfileConnectionRequestService $requestService,
        private readonly ProfileConnectionFailureMessageResolver $messageResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $form = $this->createForm(ProfileConnectionRequestType::class);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            $this->addFlash('error', $this->translator->trans('profile_connection.error.invalid_query', [], 'navigation'));

            return $this->redirectToRoute('app_profile_connection_list');
        }

        /** @var array{query: string} $data */
        $data = $form->getData();

        $this->sendRequest($user, $data['query']);

        return $this->redirectToRoute('app_profile_connection_list');
    }

    private function sendRequest(User $requester, string $query): void
    {
        try {
            $addressee = $this->lookupService->find($requester, $query);

            if (null === $addressee) {
                $this->addFlash('error', $this->translator->trans('profile_connection.error.not_found', [], 'navigation'));

                return;
            }

            $this->requestService->request($requester, $addressee);
            $this->addFlash('success', $this->translator->trans('profile_connection.flash.requested', [
                'nickname' => $addressee->nickname,
            ], 'navigation'));
        } catch (TooManyProfileSharingAttemptsException $exception) {
            $this->addFlash('error', $this->messageResolver->forRateLimit($exception));
        } catch (ProfileConnectionException $exception) {
            $this->addFlash('error', $this->messageResolver->forFailure($exception));
        }
    }
}
