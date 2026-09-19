<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\ProfileSharing;

use App\Exception\ProfileSharing\ProfileConnectionException;
use App\Exception\ProfileSharing\ProfileConnectionFailureReasonEnum;
use App\Exception\ProfileSharing\TooManyProfileSharingAttemptsException;
use App\Service\ProfileSharing\ProfileConnectionFailureMessageResolver;
use App\Service\ProfileSharing\ProfileConnectionRequestService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ProfileConnectionFailureMessageResolverTest extends TestCase
{
    /**
     * @return iterable<string, array{ProfileConnectionFailureReasonEnum}>
     */
    public static function reasons(): iterable
    {
        foreach (ProfileConnectionFailureReasonEnum::cases() as $reason) {
            yield $reason->value => [$reason];
        }
    }

    #[DataProvider('reasons')]
    public function testEachFailureReasonHasItsOwnTranslationKeyInTheNavigationDomain(ProfileConnectionFailureReasonEnum $reason): void
    {
        $message = $this->createResolver()->forFailure(new ProfileConnectionException($reason));

        self::assertStringStartsWith('profile_connection.error.' . $reason->value . '@navigation|', $message);
    }

    public function testCooldownMessageCarriesTheCooldownDuration(): void
    {
        $message = $this->createResolver()->forFailure(new ProfileConnectionException(ProfileConnectionFailureReasonEnum::COOLDOWN_ACTIVE));

        self::assertStringContainsString('"days":' . ProfileConnectionRequestService::RE_REQUEST_COOLDOWN_DAYS, $message);
    }

    public function testRateLimitMessageUsesTheSharedTranslationWithTheMinutesToWait(): void
    {
        $message = $this->createResolver()->forRateLimit(new TooManyProfileSharingAttemptsException(7));

        self::assertSame('rate_limiter.too_many_attempt@common|{"minutes":7}', $message);
    }

    private function createResolver(): ProfileConnectionFailureMessageResolver
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static fn (string $id, array $parameters = [], ?string $domain = null): string => \sprintf(
                '%s@%s|%s',
                $id,
                $domain,
                json_encode($parameters, JSON_THROW_ON_ERROR),
            ),
        );

        return new ProfileConnectionFailureMessageResolver($translator);
    }
}
