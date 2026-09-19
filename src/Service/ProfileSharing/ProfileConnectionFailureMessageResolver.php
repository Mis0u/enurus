<?php

declare(strict_types=1);

namespace App\Service\ProfileSharing;

use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Traduit les échecs du partage de profil en message affichable, dans la langue de la requête. La
 * clé se déduit de la valeur de `ProfileConnectionFailureReasonEnum`.
 */
final readonly class ProfileConnectionFailureMessageResolver
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {
    }

    public function forFailure(ProfileConnectionException $exception): string
    {
        return $this->translator->trans(
            'profile_connection.error.' . $exception->reason->value,
            $this->parametersFor($exception->reason),
            'navigation',
        );
    }

    public function forRateLimit(TooManyProfileSharingAttemptsException $exception): string
    {
        return $this->translator->trans('rate_limiter.too_many_attempt', [
            'minutes' => $exception->retryAfterMinutes,
        ], 'common');
    }

    /**
     * @return array<string, int>
     */
    private function parametersFor(ProfileConnectionFailureReasonEnum $reason): array
    {
        if (ProfileConnectionFailureReasonEnum::COOLDOWN_ACTIVE === $reason) {
            return [
                'days' => ProfileConnectionRequestService::RE_REQUEST_COOLDOWN_DAYS,
            ];
        }

        return [];
    }
}
