<?php

declare(strict_types=1);

namespace App\Exception\Translation;

/**
 * Levée par `DeepLTranslationService` sur tout échec (transport HTTP, statut non-2xx, quota
 * dépassé) — le rattrapage se fait via le retry natif de Messenger sur `SendContactBroadcastMessage`
 * (aucun ContactThread n'est encore persisté à ce stade), jamais par une logique de retry locale.
 */
final class TranslationFailedException extends \RuntimeException
{
}
