<?php

declare(strict_types=1);

namespace App\Controller\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Page affichée juste après l'inscription (TODO #24) — l'email en attente de confirmation vient de
 * la session, écrite par `RegistrationController::register()`.
 */
final class RegistrationCheckEmailController extends AbstractController
{
    #[Route(
        path: [
            'en' => '/register/check-email',
            'fr' => '/inscription/verifier-email',
            'it' => '/registrazione/controlla-email',
            'es' => '/registro/verificar-email',
            'pt' => '/registo/verificar-email',
            'de' => '/registrierung/email-pruefen',
            'nl' => '/registratie/email-controleren',
            'pl' => '/rejestracja/sprawdz-email',
        ],
        name: 'app_registration_check_email'
    )]
    public function __invoke(Request $request): Response
    {
        /** @var string|null $email */
        $email = $request->getSession()->get(RegistrationController::PENDING_EMAIL_SESSION_KEY);

        if (null === $email) {
            return $this->redirectToRoute('app_register');
        }

        return $this->render('registration/check_email.html.twig', [
            'email' => $email,
        ]);
    }
}
