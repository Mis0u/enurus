<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Security\EmailVerifier;
use App\Service\Security\UserRegistrationService;
use App\Service\Security\ValidateSecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class RegistrationController extends AbstractController
{
    private const string BOT_DETECTED = 'bot_detected';

    private const string RATE_LIMIT = 'rate_limit';

    public function __construct(
        private readonly EmailVerifier $emailVerifier,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('/inscription', name: 'app_register')]
    public function register(
        Request $request,
        Security $security,
        RateLimiterFactory $registrationLimiter,
        ValidateSecurityService $validator,
        UserRegistrationService $registrationService,
    ): Response {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }
        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $validationResult = $validator->validate($form, $request, $registrationLimiter);

            if (! $validationResult['passed']) {
                return $this->handleSecurityFailure($validationResult);
            }

            $registrationService->registerUser($user, $form);

            return $security->login($user, 'form_login', 'main')
                ?? throw new AuthenticationException(
                    'L\'utilisateur n\'a pas pu se connecter automatiquement après inscription.'
                );
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/verify/email', name: 'app_verify_email')]
    public function verifyUserEmail(Request $request, TranslatorInterface $translator): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        try {
            /** @var User $user */
            $user = $this->getUser();
            $this->emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('verify_email_error', $translator->trans($exception->getReason(), [], 'VerifyEmailBundle'));

            return $this->redirectToRoute('app_register');
        }

        // @TODO Change the redirect on success and handle or remove the flash message in your templates
        $this->addFlash('success', 'Your email address has been verified.');

        return $this->redirectToRoute('app_dashboard');
    }

    /**
     * @param array{passed: false, reason: 'rate_limit'|'bot_detected' , minutes: int} $validator
     */
    private function handleSecurityFailure(array $validator): Response
    {
        if (self::BOT_DETECTED === $validator['reason']) {
            $this->addFlash(
                'success',
                $this->translator->trans('registration.success', [], 'security')
            );
        }

        if (self::RATE_LIMIT === $validator['reason']) {
            $this->addFlash(
                'error',
                $this->translator->trans('rate_limiter.too_many_attempt', [
                    '%minutes%' => $validator['minutes'],
                ], 'security')
            );
        }

        return $this->redirectToRoute('app_login');
    }
}
