<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Form\ResetPasswordRequestFormType;
use App\Service\Security\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

final class ResetPasswordRequestController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordService $resetPasswordService,
    ) {
    }

    #[Route(path: [
        'en' => '/reset-password',
        'fr' => '/reinitialiser-mot-de-passe',
        'it' => '/reimposta-password',
        'es' => '/restablecer-contrasena',
        'pt' => '/redefinir-palavra-passe',
        'de' => '/passwort-zuruecksetzen',
        'nl' => '/wachtwoord-herstellen',
        'pl' => '/resetuj-haslo',
    ], name: 'app_forgot_password_request')]
    public function __invoke(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $email */
            $email = $form->get('email')->getData();
            try {
                $resetToken = $this->resetPasswordService->processSendingPasswordResetEmail($email, $request->getLocale());
                $this->setTokenObjectInSession($resetToken);
                return $this->redirectToRoute('app_check_email');
            } catch (ResetPasswordExceptionInterface $e) {
                return $this->redirectToRoute('app_check_email');
            }
        }

        return $this->render('reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }
}
