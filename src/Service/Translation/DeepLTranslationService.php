<?php

declare(strict_types=1);

namespace App\Service\Translation;

use App\Enum\Translations\LocaleAllowedEnum;
use App\Exception\Translation\TranslationFailedException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Traduit une liste de textes (sujet, corps, libellés d'options de sondage, ...) depuis le
 * français (langue source unique du projet, cf. CLAUDE.md) vers une langue cible, en un seul appel
 * DeepL (l'API accepte plusieurs `text[]` dans une même requête, réponse dans le même ordre). N'a
 * aucune connaissance de Doctrine/ContactBroadcast — simple wrapper HTTP à responsabilité unique.
 */
final readonly class DeepLTranslationService
{
    /**
     * DeepL exige des codes plus précis que LocaleAllowedEnum pour l'anglais et le portugais
     * (variantes régionales obligatoires) — les autres langues du projet correspondent 1:1.
     */
    private const array DEEPL_TARGET_LANG_OVERRIDES = [
        LocaleAllowedEnum::EN->value => 'EN-GB',
        LocaleAllowedEnum::PT->value => 'PT-PT',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $deeplApiKey,
        private string $deeplApiUrl,
    ) {
    }

    /**
     * @param list<string> $texts
     *
     * @return list<string> traductions dans le même ordre que $texts
     */
    public function translate(array $texts, LocaleAllowedEnum $targetLocale): array
    {
        if (LocaleAllowedEnum::FR === $targetLocale) {
            throw new \LogicException('Le français ne doit jamais être envoyé à DeepL comme langue cible.');
        }

        try {
            $response = $this->httpClient->request('POST', $this->deeplApiUrl, [
                'headers' => [
                    'Authorization' => \sprintf('DeepL-Auth-Key %s', $this->deeplApiKey),
                ],
                'json' => [
                    'text' => $texts,
                    'source_lang' => 'FR',
                    'target_lang' => $this->mapToDeeplTargetLang($targetLocale),
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if (Response::HTTP_OK !== $statusCode) {
                throw new TranslationFailedException(\sprintf('DeepL a répondu avec le statut %d.', $statusCode));
            }

            /** @var array{translations: list<array{text: string}>} $content */
            $content = $response->toArray();
        } catch (ExceptionInterface $e) {
            throw new TranslationFailedException('Échec de la requête HTTP vers DeepL.', previous: $e);
        }

        return array_map(static fn (array $translation): string => $translation['text'], $content['translations']);
    }

    private function mapToDeeplTargetLang(LocaleAllowedEnum $locale): string
    {
        return self::DEEPL_TARGET_LANG_OVERRIDES[$locale->value] ?? strtoupper($locale->value);
    }
}
