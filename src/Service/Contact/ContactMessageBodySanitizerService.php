<?php

declare(strict_types=1);

namespace App\Service\Contact;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

/**
 * Nettoie le HTML produit par l'éditeur WYSIWYG admin (Quill) avant stockage — allowlist limitée
 * aux balises que la toolbar peut réellement produire (gras/italique/souligné/listes/liens).
 * Jamais utilisé sur le texte des utilisateurs, qui reste du texte brut échappé à l'affichage.
 */
final readonly class ContactMessageBodySanitizerService
{
    private HtmlSanitizerInterface $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('ol')
            ->allowElement('ul')
            ->allowElement('li')
            ->allowElement('a', ['href', 'target', 'rel'])
            ->allowLinkSchemes(['http', 'https', 'mailto'])
        ;

        $this->sanitizer = new HtmlSanitizer($config);
    }

    public function sanitize(string $html): string
    {
        return $this->sanitizer->sanitize($html);
    }
}
