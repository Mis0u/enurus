<?php

declare(strict_types=1);

namespace App\Controller\YearInReview;

use App\Entity\User;
use App\Service\YearInReview\YearInReviewNavigationState;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Active ou coupe l'email annonçant le résumé annuel, depuis « Mes résumés » — inexistant, comme la
 * page, avant la première publication.
 */
#[IsGranted('ROLE_USER')]
final class YearInReviewEmailToggleController extends AbstractController
{
    public const string CSRF_TOKEN_ID = 'year_in_review_email_toggle';

    public function __construct(
        private readonly YearInReviewNavigationState $navigationState,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route(
        path: [
            'fr' => '/mes-resumes/email',
            'en' => '/my-recaps/email',
            'it' => '/i-miei-riepiloghi/email',
            'es' => '/mis-resumenes/email',
            'pt' => '/meus-resumos/email',
            'de' => '/meine-rueckblicke/email',
            'nl' => '/mijn-overzichten/email',
            'pl' => '/moje-podsumowania/email',
        ],
        name: 'app_year_in_review_email_toggle',
        methods: [Request::METHOD_PATCH],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->navigationState->isVisible()) {
            throw $this->createNotFoundException();
        }

        /** @var array{enabled?: bool, _token?: string} $payload */
        $payload = json_decode($request->getContent(), true) ?? [];

        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, $payload['_token'] ?? '')) {
            return $this->json([
                'error' => 'Invalid CSRF token',
            ], Response::HTTP_FORBIDDEN);
        }

        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $user->emailOnYearInReview = (bool) ($payload['enabled'] ?? false);
        $this->em->flush();

        return $this->json([
            'enabled' => $user->emailOnYearInReview,
        ]);
    }
}
