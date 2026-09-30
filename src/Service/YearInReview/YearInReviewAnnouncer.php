<?php

declare(strict_types=1);

namespace App\Service\YearInReview;

use App\Entity\User;
use App\Entity\YearInReview;
use App\Service\Email\EmailInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Email « Ton année est prête », une seule fois par résumé éligible, dans la langue de son
 * propriétaire, sauf s'il l'a coupé depuis « Mes résumés ». Aucun chiffre dans l'email : la
 * surprise reste pour les écrans. Un échec d'envoi est loggé et laisse `emailedAt` vide, pour qu'un
 * passage suivant retente l'envoi.
 *
 * Non `final` : doublé par les tests des handlers d'annonce et de génération.
 */
readonly class YearInReviewAnnouncer
{
    private const string TEMPLATE = 'emails/year_in_review_announcement.html.twig';

    public function __construct(
        private EmailInterface $emailService,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
    ) {
    }

    public function announce(YearInReview $review): void
    {
        if (! $this->shouldAnnounce($review)) {
            return;
        }

        try {
            $this->emailService->sendEmail($this->createEmail($review));
        } catch (TransportExceptionInterface $exception) {
            $this->logger->error('Failed to send the year in review announcement.', [
                'yearInReviewId' => $review->id,
                'exception' => $exception,
            ]);

            return;
        }

        $review->emailedAt = $this->clock->now();
        $this->em->flush();
    }

    private function shouldAnnounce(YearInReview $review): bool
    {
        $owner = $review->owner;

        return $review->isEligible() && null === $review->emailedAt && $this->acceptsTheEmail($owner);
    }

    private function acceptsTheEmail(User $owner): bool
    {
        return $owner->emailOnYearInReview && $owner->isVerified && null === $owner->deletionRequestedAt;
    }

    private function createEmail(YearInReview $review): TemplatedEmail
    {
        $owner = $review->owner;

        return $this->emailService->createEmail(
            $owner->email,
            $this->translator->trans('year_in_review.email.subject', [
                'year' => $review->year,
            ], 'navigation', $owner->locale),
            [
                'year' => $review->year,
                'locale' => $owner->locale,
            ],
            self::TEMPLATE,
            $owner->locale,
        );
    }
}
