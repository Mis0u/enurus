<?php

declare(strict_types=1);

namespace App\Service\Email;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class SymfonyMailerEmailService implements EmailInterface
{
    private const string FROM_EMAIL = 'fittracker@gmail.com';

    public function __construct(
        private TranslatorInterface $translator,
        private MailerInterface $mailer,
    ) {
    }

    /**
     *@param array<string, mixed> $context
     */
    public function createEmail(string $to, string $subject, array $context, string $template, string $locale): TemplatedEmail
    {
        return new TemplatedEmail()
            ->from(new Address(
                self::FROM_EMAIL,
                $this->translator->trans('name', [], 'brand', $locale)
            ))
            ->to($to)
            ->subject($subject)
            ->context($context)
            ->htmlTemplate($template);
    }

    public function sendEmail(TemplatedEmail $templatedEmail): void
    {
        $this->mailer->send($templatedEmail);
    }
}
