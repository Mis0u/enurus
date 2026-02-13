<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Service\Security\ResetPasswordService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\InvalidResetPasswordTokenException;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ResetPasswordService $resetPasswordService,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request): Response
    {
        if (null !== $this->getUser()) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->buildForm(ResetPasswordRequestFormType::class, $request);

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

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route('/check-email', name: 'app_check_email')]
    public function checkEmail(): Response
    {
        $resetToken = $this->resetPasswordService->resolveResetToken($this->getTokenObjectFromSession());

        return $this->render('reset_password/check_email.html.twig', [
            'resetToken' => $resetToken,
        ]);
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function reset(Request $request, ?string $token = null): Response
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
            $this->addFlash('error', sprintf(
                '%s - %s',
                $this->translator->trans('reset_password_request.message_problem_validate', [], 'security'),
                $this->translator->trans($e->getReason(), [], 'ResetPasswordBundle')
            ));
            return $this->redirectToRoute('app_forgot_password_request');
        }

        $form = $this->buildForm(ChangePasswordFormType::class, $request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var string $plainPassword */
            $plainPassword = $form->get('plainPassword')->getData();
            $this->resetPasswordService->resetPassword($token, $plainPassword, $user);
            $this->cleanSessionAfterReset();
            $this->addFlash('success', $this->translator->trans('sentence.password.security.confirmation', [], 'common'));
            return $this->redirectToRoute('app_login');
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

    /**
     * @template TData of mixed
     * @param class-string<FormTypeInterface<TData>> $type
     * @return FormInterface<TData>
     */
    private function buildForm(string $type, Request $request): FormInterface
    {
        /** @var FormInterface<TData> $form */
        $form = $this->createForm($type);
        $form->handleRequest($request);

        return $form;
    }
}
