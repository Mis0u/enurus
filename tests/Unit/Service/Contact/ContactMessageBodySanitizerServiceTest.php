<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Contact;

use App\Service\Contact\ContactMessageBodySanitizerService;
use PHPUnit\Framework\TestCase;

final class ContactMessageBodySanitizerServiceTest extends TestCase
{
    private ContactMessageBodySanitizerService $sanitizer;

    protected function setUp(): void
    {
        $this->sanitizer = new ContactMessageBodySanitizerService();
    }

    public function testKeepsAllowedFormattingTags(): void
    {
        $html = '<p>Bonjour <strong>toi</strong>, <em>voici</em> une <u>info</u>.</p><ul><li>Un</li><li>Deux</li></ul>';

        self::assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function testKeepsLinksWithHref(): void
    {
        $html = '<p>Voir <a href="https://fittracker.test">le site</a>.</p>';

        self::assertSame($html, $this->sanitizer->sanitize($html));
    }

    public function testStripsScriptTags(): void
    {
        $result = $this->sanitizer->sanitize('<p>Salut</p><script>alert("xss")</script>');

        self::assertStringNotContainsString('<script', $result);
        self::assertStringNotContainsString('alert', $result);
    }

    public function testStripsDisallowedElementsButKeepsTextContent(): void
    {
        $result = $this->sanitizer->sanitize('<img src="x" onerror="alert(1)"><p>Texte</p>');

        self::assertStringNotContainsString('<img', $result);
        self::assertStringNotContainsString('onerror', $result);
        self::assertStringContainsString('<p>Texte</p>', $result);
    }

    public function testStripsEventHandlerAttributes(): void
    {
        $result = $this->sanitizer->sanitize('<p onclick="alert(1)">Texte</p>');

        self::assertStringNotContainsString('onclick', $result);
        self::assertStringContainsString('Texte', $result);
    }

    public function testDropsNonHttpLinkSchemes(): void
    {
        $result = $this->sanitizer->sanitize('<a href="javascript:alert(1)">clic</a>');

        self::assertStringNotContainsString('javascript:', $result);
    }
}
