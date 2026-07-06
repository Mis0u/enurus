<?php

declare(strict_types=1);

namespace App\Controller\Settings;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_USER')]
final class SettingsNicknameController extends AbstractController
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'en' => '/settings/nickname',
            'fr' => '/reglages/pseudo',
            'it' => '/impostazioni/soprannome',
            'es' => '/ajustes/apodo',
            'pt' => '/definicoes/alcunha',
            'de' => '/einstellungen/spitzname',
            'nl' => '/instellingen/bijnaam',
            'pl' => '/ustawienia/pseudonim',
        ],
        name: 'app_settings_nickname_update',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array{nickname?: string, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid('settings_nickname', $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $nickname = trim($payload['nickname'] ?? '');

        $violations = $this->validator->validate($nickname, [
            new NotBlank(),
            new Length(
                min: User::NICKNAME_MIN_LENGTH,
                max: User::NICKNAME_MAX_LENGTH,
                minMessage: 'user.nickname.length.min',
                maxMessage: 'user.nickname.length.max',
            ),
        ]);

        if (0 < \count($violations)) {
            return $this->json(
                [
                    'errors' => [(string) $violations->get(0)->getMessage()],
                ],
                Response::HTTP_UNPROCESSABLE_ENTITY,
            );
        }

        /** @var User $user */
        $user = $this->getUser();
        $user->nickname = $nickname;
        $this->em->flush();

        return $this->json([
            'nickname' => $user->nickname,
        ]);
    }
}
