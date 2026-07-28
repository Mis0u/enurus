<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Service\Security\UserRegistrationService;
use App\Service\Security\ValidateSecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    /**
     * Clé de session portant l'email en attente de confirmation — lue par
     * `RegistrationCheckEmailController` et `RegistrationResendConfirmationController` (TODO #24 :
     * plus de connexion auto à l'inscription, le compte reste inactif jusqu'au clic sur le lien).
     */
    public const string PENDING_EMAIL_SESSION_KEY = 'registration_pending_email';

    private const string BOT_DETECTED = 'bot_detected';

    private const string RATE_LIMIT = 'rate_limit';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ValidateSecurityService $validator,
        private readonly UserRegistrationService $registrationService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/register',
            'fr' => '/inscription',
            'it' => '/registrazione',
            'es' => '/registro',
            'pt' => '/registo',
            'de' => '/registrierung',
            'nl' => '/registratie',
            'pl' => '/rejestracja',
        ],
        name: 'app_register'
    )]
    public function register(
        Request $request,
        RateLimiterFactory $registrationLimiter,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $validationResult = $this->validator->validate($form, $request, $registrationLimiter);

            if (! $validationResult['passed']) {
                return $this->handleSecurityFailure($validationResult);
            }
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $this->registrationService->registerUser($user, $plainPassword, $request->getLocale());

            $request->getSession()->set(self::PENDING_EMAIL_SESSION_KEY, $user->email);

            return $this->redirectToRoute('app_registration_check_email');
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    /**
     * @param array{passed: false, reason: 'rate_limit'|'bot_detected' , minutes: int} $validator
     */
    private function handleSecurityFailure(array $validator): Response
    {
        if (self::BOT_DETECTED === $validator['reason']) {
            $this->addFlash(
                'success',
                $this->translator->trans('registration.success', [], 'navigation')
            );
        }

        if (self::RATE_LIMIT === $validator['reason']) {
            $this->addFlash(
                'error',
                $this->translator->trans('rate_limiter.too_many_attempt', [
                    'minutes' => $validator['minutes'],
                ], 'common')
            );
        }

        return $this->redirectToRoute('app_login');
    }
}
