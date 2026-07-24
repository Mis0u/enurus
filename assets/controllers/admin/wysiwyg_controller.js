import { Controller } from '@hotwired/stimulus';
import Quill from 'quill';
import 'quill/dist/quill.snow.css';

export default class extends Controller {
    static targets = ['editor', 'input'];

    connect() {
        this.quill = new Quill(this.editorTarget, {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ list: 'ordered' }, { list: 'bullet' }],
                    ['link'],
                    ['clean'],
                ],
            },
        });

        this.quill.root.innerHTML = this.inputTarget.value;
        this.quill.on('text-change', () => this.#sync());
    }

    #sync() {
        this.inputTarget.value = '' === this.quill.getText().trim() ? '' : this.#normalizeLinks(this.quill.root.innerHTML);
    }

    /**
     * Le popup lien de Quill n'impose aucun schéma — un admin qui tape "www.koreus.com" produit un
     * href sans http(s), que le sanitizer backend (Symfony HtmlSanitizer, allowRelativeLinks à
     * false) considère comme relatif et retire, laissant un <a> sans lien mais toujours souligné.
     * target="_blank" + rel="noopener noreferrer" (nouvel onglet, sans reverse tabnabbing) posés
     * ici une fois pour toutes plutôt qu'à chaque affichage — le body stocké est déjà le HTML final.
     */
    #normalizeLinks(html) {
        const template = document.createElement('template');
        template.innerHTML = html;

        template.content.querySelectorAll('a[href]').forEach((anchor) => {
            const href = anchor.getAttribute('href');
            if (! /^(https?:|mailto:)/i.test(href)) {
                anchor.setAttribute('href', `https://${href}`);
            }

            anchor.setAttribute('target', '_blank');
            anchor.setAttribute('rel', 'noopener noreferrer');
        });

        return template.innerHTML;
    }
}
