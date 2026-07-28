<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Repository\UserRepository;
use App\Service\Security\EmailVerificationService;
use App\Service\Security\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Renvoi de l'email de confirmation depuis la page "vérifie ta boîte mail" (TODO #24) — l'email
 * vient de la session (jamais d'un champ utilisateur), donc pas de risque d'énumération. Limité en
 * IP via `email_confirmation_resend` (cf. `config/packages/rate_limiter.yaml`).
 */
final class RegistrationResendConfirmationController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailVerificationService $emailVerificationService,
        private readonly RateLimiterService $rateLimiterService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route(
        path: [
            'en' => '/register/resend-confirmation',
            'fr' => '/inscription/renvoyer-confirmation',
            'it' => '/registrazione/reinvia-conferma',
            'es' => '/registro/reenviar-confirmacion',
            'pt' => '/registo/reenviar-confirmacao',
            'de' => '/registrierung/bestaetigung-erneut-senden',
            'nl' => '/registratie/bevestiging-opnieuw-versturen',
            'pl' => '/rejestracja/wyslij-ponownie-potwierdzenie',
        ],
        name: 'app_registration_resend_confirmation',
        methods: [Request::METHOD_POST],
    )]
    public function __invoke(Request $request, RateLimiterFactory $emailConfirmationResendLimiter): Response
    {
        /** @var string|null $email */
        $email = $request->getSession()->get(RegistrationController::PENDING_EMAIL_SESSION_KEY);

        if (null === $email) {
            return $this->redirectToRoute('app_register');
        }

        $token = $request->request->get('_token');

        if (! $this->isCsrfTokenValid('registration_resend_confirmation', \is_string($token) ? $token : null)) {
            throw new AccessDeniedHttpException('Invalid CSRF token.');
        }

        /** @var string $ipClient */
        $ipClient = $request->getClientIp();
        $result = $this->rateLimiterService->checkLimit($emailConfirmationResendLimiter, $ipClient);

        if (! $result['accepted']) {
            $this->addFlash('error', $this->translator->trans('rate_limiter.too_many_attempt', [
                'minutes' => (int) $result['minutes'],
            ], 'common'));

            return $this->redirectToRoute('app_registration_check_email');
        }

        $user = $this->userRepository->findOneByEmail($email);

        if (null !== $user && ! $user->isVerified) {
            $this->emailVerificationService->sendConfirmationEmail($user, $request->getLocale());
        }

        $this->addFlash('success', $this->translator->trans('sentence.email.email_send', [], 'common'));

        return $this->redirectToRoute('app_registration_check_email');
    }
}
