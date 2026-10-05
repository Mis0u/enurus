<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * En-têtes de sécurité posés sur toute réponse principale, pages d'erreur comprises. Le site ne
 * s'intègre jamais dans une iframe (ni lui-même, ni ailleurs) : son intégration est interdite, ce
 * qui empêche de piéger un clic (« Supprimer », « Accepter ») sous une page tierce.
 *
 * Pas de CSP complète ici : scripts et styles inline (Tailwind, Stimulus, SweetAlert2, Quill)
 * demanderaient un chantier dédié. HSTS est posé par Cloudflare.
 */
final readonly class SecurityHeadersListener
{
    private const array HEADERS = [
        'X-Frame-Options' => 'DENY',
        'Content-Security-Policy' => "frame-ancestors 'none'",
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    #[AsEventListener]
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;

        foreach (self::HEADERS as $name => $value) {
            if (! $headers->has($name)) {
                $headers->set($name, $value);
            }
        }
    }
}
