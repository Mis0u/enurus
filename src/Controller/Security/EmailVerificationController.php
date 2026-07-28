<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\Security\EmailVerificationService;
use App\Service\Security\UserRegistrationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

/**
 * Clic sur le lien de confirmation reçu à l'inscription (TODO #24). Route publique — l'utilisateur
 * n'est pas encore authentifié à ce stade, donc pas de `#[IsGranted('ROLE_USER')]`.
 */
final class EmailVerificationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly UserRegistrationService $registrationService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: [
            'en' => '/verify-email',
            'fr' => '/verifier-email',
            'it' => '/verifica-email',
            'es' => '/verificar-email',
            'pt' => '/verificar-email',
            'de' => '/email-bestaetigen',
            'nl' => '/email-verifieren',
            'pl' => '/potwierdz-email',
        ],
        name: 'app_verify_email'
    )]
    public function __invoke(Request $request, Security $security): Response
    {
        $user = $this->findUserFromRequest($request);

        if (null === $user) {
            $this->addFlash('error', $this->translator->trans(
                'The link to verify your email is invalid. Please request a new link.',
                [],
                'VerifyEmailBundle'
            ));

            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified) {
            return $this->redirectToRoute('app_login');
        }

        try {
            $this->emailVerificationService->confirmEmail($request, $user);
        } catch (VerifyEmailExceptionInterface $e) {
            $this->addFlash('error', $this->translator->trans($e->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_registration_check_email');
        }

        $this->registrationService->completeRegistration($user, $request->getLocale());

        return $security->login($user, 'form_login', 'main')
            ?? $this->redirectToRoute('app_dashboard');
    }

    private function findUserFromRequest(Request $request): ?User
    {
        $id = $request->query->get('id');

        if (! \is_string($id) || ! Uuid::isValid($id)) {
            return null;
        }

        return $this->userRepository->find(Uuid::fromString($id));
    }
}
