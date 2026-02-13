<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;

readonly class EmailService
{
    private const string FROM_EMAIL = 'fittracker@gmail.com';

    public function __construct(
        private TranslatorInterface $translator,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function createRegistrationConfirmationEmail(User $user, string $locale): TemplatedEmail
    {
        return $this->createEmail(
            (string) $user->getEmail(),
            $this->translator->trans('registration.confirm_email', [], 'security', $locale),
            [
                'locale' => $locale,
            ],
            'registration/confirmation_email.html.twig',
            $locale
        );
    }

    /**
     *@param array<string, string|ResetPasswordToken> $context
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
