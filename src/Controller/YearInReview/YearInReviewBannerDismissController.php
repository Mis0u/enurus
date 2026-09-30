<?php

declare(strict_types=1);

namespace App\Controller\YearInReview;

use App\Controller\Trait\ValidatesDeleteRequestTrait;
use App\Entity\User;
use App\Repository\YearInReviewRepository;
use App\Service\YearInReview\YearInReviewCalendar;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Croix du bandeau « Ton année est prête » du dashboard : il ne revient plus pour cette année. Mêmes
 * gardes XHR + CSRF que les suppressions ; 404 sans résumé éligible publié pour cette année.
 */
#[IsGranted('ROLE_USER')]
final class YearInReviewBannerDismissController extends AbstractController
{
    use ValidatesDeleteRequestTrait;

    public const string CSRF_TOKEN_ID = 'year_in_review_banner_dismiss';

    public function __construct(
        private readonly YearInReviewCalendar $calendar,
        private readonly YearInReviewRepository $yearInReviewRepository,
        private readonly EntityManagerInterface $em,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route(
        path: [
            'fr' => '/mes-resumes/{year}/bandeau',
            'en' => '/my-recaps/{year}/banner',
            'it' => '/i-miei-riepiloghi/{year}/banner',
            'es' => '/mis-resumenes/{year}/banner',
            'pt' => '/meus-resumos/{year}/faixa',
            'de' => '/meine-rueckblicke/{year}/banner',
            'nl' => '/mijn-overzichten/{year}/banner',
            'pl' => '/moje-podsumowania/{year}/baner',
        ],
        name: 'app_year_in_review_banner_dismiss',
        requirements: [
            'year' => '\d{4}',
        ],
        methods: [Request::METHOD_POST],
    )]
    public function __invoke(Request $request, int $year): JsonResponse
    {
        $notXhr = $this->denyUnlessXmlHttpRequest($request);

        if (null !== $notXhr) {
            return $notXhr;
        }

        $this->denyUnlessValidCsrfToken($request, self::CSRF_TOKEN_ID);

        $user = $this->getUser();

        if (! $user instanceof User) {
            throw new \LogicException('User must be authenticated.');
        }

        $review = $this->calendar->isPublished($year) ? $this->yearInReviewRepository->findEligibleByOwnerAndYear($user, $year) : null;

        if (null === $review) {
            throw $this->createNotFoundException();
        }

        $review->bannerDismissedAt ??= $this->clock->now();
        $this->em->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }
}
