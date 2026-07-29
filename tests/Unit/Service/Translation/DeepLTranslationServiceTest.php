<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Translation;

use App\Enum\Translations\LocaleAllowedEnum;
use App\Exception\Translation\TranslationFailedException;
use App\Service\Translation\DeepLTranslationService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class DeepLTranslationServiceTest extends TestCase
{
    public function testTranslateReturnsSubjectAndBodyInOrder(): void
    {
        $capturedOptions = null;

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedOptions): MockResponse {
            $capturedOptions = $options;

            return new MockResponse(json_encode([
                'translations' => [
                    [
                        'text' => 'Betreff',
                    ],
                    [
                        'text' => 'Inhalt',
                    ],
                ],
            ], JSON_THROW_ON_ERROR));
        });

        $service = new DeepLTranslationService($httpClient, 'fake-key', 'https://api-free.deepl.com/v2/translate');

        [$subject, $body] = $service->translate(['Sujet', 'Corps'], LocaleAllowedEnum::DE);

        self::assertSame('Betreff', $subject);
        self::assertSame('Inhalt', $body);

        self::assertIsArray($capturedOptions);
        self::assertSame('DeepL-Auth-Key fake-key', $this->findHeader($capturedOptions, 'Authorization'));

        /** @var array{text: list<string>, source_lang: string, target_lang: string} $payload */
        $payload = json_decode((string) $capturedOptions['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['Sujet', 'Corps'], $payload['text']);
        self::assertSame('FR', $payload['source_lang']);
        self::assertSame('DE', $payload['target_lang']);
    }

    public function testTranslateMapsEnglishAndPortugueseToRegionalVariants(): void
    {
        $targetLangs = [];

        $httpClient = new MockHttpClient(function (string $method, string $url, array $options) use (&$targetLangs): MockResponse {
            /** @var array{target_lang: string} $payload */
            $payload = json_decode((string) $options['body'], true, 512, JSON_THROW_ON_ERROR);
            $targetLangs[] = $payload['target_lang'];

            return new MockResponse(json_encode([
                'translations' => [[
                    'text' => 'a',
                ], [
                    'text' => 'b',
                ]],
            ], JSON_THROW_ON_ERROR));
        });

        $service = new DeepLTranslationService($httpClient, 'fake-key', 'https://api-free.deepl.com/v2/translate');

        $service->translate(['s', 'b'], LocaleAllowedEnum::EN);
        $service->translate(['s', 'b'], LocaleAllowedEnum::PT);

        self::assertSame(['EN-GB', 'PT-PT'], $targetLangs);
    }

    public function testTranslateReturnsAllTextsInOrderIncludingExtraEntries(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse(json_encode([
            'translations' => [
                [
                    'text' => 'Betreff',
                ],
                [
                    'text' => 'Inhalt',
                ],
                [
                    'text' => 'Option A',
                ],
                [
                    'text' => 'Option B',
                ],
            ],
        ], JSON_THROW_ON_ERROR)));

        $service = new DeepLTranslationService($httpClient, 'fake-key', 'https://api-free.deepl.com/v2/translate');

        $translations = $service->translate(['Sujet', 'Corps', 'Option A', 'Option B'], LocaleAllowedEnum::DE);

        self::assertSame(['Betreff', 'Inhalt', 'Option A', 'Option B'], $translations);
    }

    public function testTranslateThrowsWhenFrenchIsRequestedAsTargetLocale(): void
    {
        $service = new DeepLTranslationService(new MockHttpClient(), 'fake-key', 'https://api-free.deepl.com/v2/translate');

        $this->expectException(\LogicException::class);
        $service->translate(['Sujet', 'Corps'], LocaleAllowedEnum::FR);
    }

    public function testTranslateWrapsNonSuccessStatusIntoTranslationFailedException(): void
    {
        $httpClient = new MockHttpClient(static fn (): MockResponse => new MockResponse('', [
            'http_code' => 403,
        ]));

        $service = new DeepLTranslationService($httpClient, 'invalid-key', 'https://api-free.deepl.com/v2/translate');

        $this->expectException(TranslationFailedException::class);
        $service->translate(['Sujet', 'Corps'], LocaleAllowedEnum::DE);
    }

    public function testTranslateWrapsTransportExceptionIntoTranslationFailedException(): void
    {
        $httpClient = new MockHttpClient(static function (): MockResponse {
            throw new TransportException('Network unreachable.');
        });

        $service = new DeepLTranslationService($httpClient, 'fake-key', 'https://api-free.deepl.com/v2/translate');

        $this->expectException(TranslationFailedException::class);
        $service->translate(['Sujet', 'Corps'], LocaleAllowedEnum::DE);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function findHeader(array $options, string $name): ?string
    {
        /** @var list<string> $headers */
        $headers = $options['headers'] ?? [];

        foreach ($headers as $header) {
            if (str_starts_with($header, $name . ':')) {
                return trim(substr($header, \strlen($name) + 1));
            }
        }

        return null;
    }
}
