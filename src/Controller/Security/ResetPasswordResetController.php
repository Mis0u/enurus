<?php

declare(strict_types=1);

namespace App\Controller\Security;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Service\Security\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\InvalidResetPasswordTokenException;

/**
 * Valide et traite l'URL de réinitialisation sur laquelle l'utilisateur a cliqué dans son email.
 */
final class ResetPasswordResetController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ResetPasswordService $resetPasswordService,
    ) {
    }

    #[Route(path: [
        'en' => '/reset-password/reset/{token}',
        'fr' => '/reinitialiser-mot-de-passe/reinitialiser/{token}',
        'it' => '/reimposta-password/reimposta/{token}',
        'es' => '/restablecer-contrasena/restablecer/{token}',
        'pt' => '/redefinir-palavra-passe/redefinir/{token}',
        'de' => '/passwort-zuruecksetzen/zuruecksetzen/{token}',
        'nl' => '/wachtwoord-herstellen/herstellen/{token}',
        'pl' => '/resetuj-haslo/resetuj/{token}',
    ], name: 'app_reset_password')]
    public function __invoke(Request $request, ?string $token = null): Response
    {
        if ($token) {
            return $this->storeTokenAndRedirect($token);
        }

        $token = $this->getTokenFromSession();

        if (null === $token) {
            throw $this->createNotFoundException('No reset password token found in the URL or in the session.');
        }

        $resetToken = $this->resetPasswordService->resolveResetToken($this->getTokenObjectFromSession());

        try {
            $user = $this->resetPasswordService->validateToken($token);
        } catch (InvalidResetPasswordTokenException $e) {
            return $this->flashError($e);
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            return $this->resetPasswordAndRedirect($token, $plainPassword, $user);
        }

        return $this->render('reset_password/reset.html.twig', [
            'resetForm' => $form,
            'resetToken' => $resetToken,
        ]);
    }

    private function storeTokenAndRedirect(string $token): RedirectResponse
    {
        // We store the token in session and remove it from the URL, to avoid the URL being
        // loaded in a browser and potentially leaking the token to 3rd party JavaScript.
        $this->storeTokenInSession($token);
        return $this->redirectToRoute('app_reset_password');
    }

    private function flashError(InvalidResetPasswordTokenException $e): RedirectResponse
    {
        $this->addFlash('error', sprintf(
            '%s - %s',
            $this->translator->trans('reset_password_request.message_problem_validate', [], 'navigation'),
            $this->translator->trans($e->getReason(), [], 'ResetPasswordBundle')
        ));
        return $this->redirectToRoute('app_forgot_password_request');
    }

    private function resetPasswordAndRedirect(string $token, string $plainPassword, User $user): RedirectResponse
    {
        $this->resetPasswordService->resetPassword($token, $plainPassword, $user);
        $this->cleanSessionAfterReset();
        $this->addFlash('success', $this->translator->trans('sentence.password.security.confirmation', [], 'common'));
        return $this->redirectToRoute('app_login');
    }
}
