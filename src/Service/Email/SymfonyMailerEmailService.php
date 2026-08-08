<?php

declare(strict_types=1);

namespace App\Service\Email;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class SymfonyMailerEmailService implements EmailInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private MailerInterface $mailer,
        private string $fromEmail,
    ) {
    }

    /**
     *@param array<string, mixed> $context
     */
    public function createEmail(string $to, string $subject, array $context, string $template, string $locale): TemplatedEmail
    {
        return new TemplatedEmail()
            ->from(new Address(
                $this->fromEmail,
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
