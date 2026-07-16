<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class ValidateSecurityService
{
    public function __construct(
        private BotDetectionService $botDetectionService,
        private RateLimiterService $rateLimiterService,
    ) {
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     * @return array{passed: true}|array{passed: false, reason: 'rate_limit'|'bot_detected', minutes: int}
     */
    public function validate(
        FormInterface $form,
        Request $request,
        RateLimiterFactory $rateLimiterFactory
    ): array {
        $rateLimitResult = $this->checkRateLimit($request, $rateLimiterFactory);
        if (! $rateLimitResult['passed']) {
            return $rateLimitResult;
        }

        $botResult = $this->checkBot($form);
        if (! $botResult['passed']) {
            return $botResult;
        }

        return [
            'passed' => true,
        ];
    }

    /**
     * @return array{passed: true}|array{passed: false, reason: 'rate_limit', minutes: int}
     */
    private function checkRateLimit(Request $request, RateLimiterFactory $rateLimiterFactory): array
    {
        /** @var string $ipClient */
        $ipClient = $request->getClientIp();
        $result = $this->rateLimiterService->checkLimit($rateLimiterFactory, $ipClient);

        if (! $result['accepted']) {
            return [
                'passed' => false,
                'reason' => 'rate_limit',
                'minutes' => (int) $result['minutes'],
            ];
        }

        return [
            'passed' => true,
        ];
    }

    /**
     * @param FormInterface<mixed> $form
     * @return array{passed: true}|array{passed: false, reason: 'bot_detected', minutes: int}
     */
    private function checkBot(FormInterface $form): array
    {
        if ($this->botDetectionService->isBot($form)) {
            return [
                'passed' => false,
                'reason' => 'bot_detected',
                'minutes' => 0,
            ];
        }

        return [
            'passed' => true,
        ];
    }
}
