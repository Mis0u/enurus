<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Form\FormInterface;

final readonly class BotDetectionService
{
    private const int MIN_FORM_DURATION_SECONDS = 3;

    public function __construct(
        private ClockInterface $clock
    ) {
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    public function isBot(FormInterface $form): bool
    {
        return $this->isSubmittedTooFast($form) || $this->isHoneypotFilled($form);
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function isSubmittedTooFast(FormInterface $form): bool
    {
        /** @var int $submitFormDuration */
        $submitFormDuration = $form->get('formStarted')->getData();
        $now = $this->clock->now()->getTimestamp();

        return ($now - $submitFormDuration) < self::MIN_FORM_DURATION_SECONDS;
    }

    /**
     * @template TData
     * @param FormInterface<TData> $form
     */
    private function isHoneypotFilled(FormInterface $form): bool
    {
        $honeyPot = $form->get('website')->getData();
        return ! empty($honeyPot);
    }
}
