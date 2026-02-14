<?php

declare(strict_types=1);

namespace App\Service\Email;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;

interface EmailInterface
{
    /**
     *@param array<string, mixed> $context
     */
    public function createEmail(string $to, string $subject, array $context, string $template, string $locale): TemplatedEmail;

    public function sendEmail(TemplatedEmail $templatedEmail): void;
}
