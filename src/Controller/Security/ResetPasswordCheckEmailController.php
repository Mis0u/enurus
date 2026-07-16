<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Service\Security\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;

/**
 * Page de confirmation affichée après qu'un utilisateur a demandé une réinitialisation de mot de
 * passe.
 */
final class ResetPasswordCheckEmailController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordService $resetPasswordService,
    ) {
    }

    #[Route(path: [
        'en' => '/reset-password/check-email',
        'fr' => '/reinitialiser-mot-de-passe/verifier-email',
        'it' => '/reimposta-password/controlla-email',
        'es' => '/restablecer-contrasena/verificar-email',
        'pt' => '/redefinir-palavra-passe/verificar-email',
        'de' => '/passwort-zuruecksetzen/email-pruefen',
        'nl' => '/wachtwoord-herstellen/email-controleren',
        'pl' => '/resetuj-haslo/sprawdz-email',
    ], name: 'app_check_email')]
    public function __invoke(): Response
    {
        $resetToken = $this->resetPasswordService->resolveResetToken($this->getTokenObjectFromSession());

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }
}
