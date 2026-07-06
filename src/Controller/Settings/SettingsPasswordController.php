<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Service\Entity\UserService;
use App\Service\Security\RateLimiterService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_USER')]
final class SettingsPasswordController extends AbstractController
{
    public function __construct(
        private readonly UserService $userService,
        private readonly TranslatorInterface $translator,
        private readonly RateLimiterService $rateLimiterService,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/password',
            'fr' => '/reglages/mot-de-passe',
            'it' => '/impostazioni/password',
            'es' => '/ajustes/contrasena',
            'pt' => '/definicoes/palavra-passe',
            'de' => '/einstellungen/passwort',
            'nl' => '/instellingen/wachtwoord',
            'pl' => '/ustawienia/haslo',
        ],
        name: 'app_settings_password_update',
        methods: ['POST'],
    )]
    public function __invoke(Request $request, RateLimiterFactory $passwordChangeLimiter): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (null === $user->id) {
            throw new \LogicException('Cannot rate-limit password change for a user without a persisted id.');
        }

        $limitResult = $this->rateLimiterService->checkLimit($passwordChangeLimiter, $user->id->toRfc4122());

        if (! $limitResult['accepted']) {
            return $this->json(
                [
                    'error' => $this->translator->trans('rate_limiter.too_many_attempt', [
                        'minutes' => $limitResult['minutes'],
                    ], 'security'),
                ],
                Response::HTTP_TOO_MANY_REQUESTS,
            );
        }

        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if (! $form->isSubmitted() || ! $form->isValid()) {
            return $this->json(
                [
                    'errors' => $this->extractFormErrors($form),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var string $currentPassword */
        $currentPassword = $form->get('currentPassword')->getData();
        /** @var string $newPassword */
        $newPassword = $form->get('plainPassword')->getData();

        $success = $this->userService->changePassword($user, $currentPassword, $newPassword);

        if (! $success) {
            $form->get('currentPassword')->addError(
                new FormError($this->translator->trans('settings.password.current_password_invalid', [], 'navigation')),
            );

            return $this->json(
                [
                    'errors' => $this->extractFormErrors($form),
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        return $this->json([
            'success' => true,
        ]);
    }

    /**
     * @param FormInterface<null> $form
     * @return array<string, list<string>>
     */
    private function extractFormErrors(FormInterface $form): array
    {
        $errors = [];

        foreach ($form as $child) {
            foreach ($child->getErrors() as $error) {
                $errors[$child->getName()][] = $error->getMessage();
            }
        }

        return $errors;
    }
}
