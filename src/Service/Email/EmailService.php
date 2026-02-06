<?php

declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EmailService
{
    private const string FROM_EMAIL = 'fittracker@gmail.com';

    public function __construct(
        private TranslatorInterface $translator
    ) {
    }

    public function createRegistrationConfirmationEmail(User $user): TemplatedEmail
    {
        return $this->createEmail(
            (string) $user->getEmail(),
            $this->translator->trans('registration.confirm_email', [], 'security'),
            'registration/confirmation_email.html.twig'
        );
    }

    /**
     * Crée un email générique
     */
    public function createEmail(string $to, string $subject, string $template): TemplatedEmail
    {
        return new TemplatedEmail()
            ->from(new Address(
                self::FROM_EMAIL,
                $this->translator->trans('name', [], 'brand')
            ))
            ->to($to)
            ->subject($subject)
            ->htmlTemplate($template);
    }
}
