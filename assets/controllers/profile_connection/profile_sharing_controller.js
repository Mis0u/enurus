import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['code', 'codeWrapper'];

    static values = {
        toggleUrl: String,
        regenerateUrl: String,
        toggleToken: String,
        regenerateToken: String,
        enableMessage: String,
        disableMessage: String,
        regenerateMessage: String,
        copyMessage: String,
    };

    async toggle(event) {
        const isDiscoverable = event.target.checked;

        try {
            const response = await fetch(this.toggleUrlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ isDiscoverable, _token: this.toggleTokenValue }),
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            this.#applyShareCode(data.shareCode);
            showSuccessToast(isDiscoverable ? this.enableMessageValue : this.disableMessageValue);
        } catch {
            // Échec silencieux volontaire — même pattern que dashboard_widgets_controller.js.
        }
    }

    async regenerate() {
        try {
            const response = await fetch(this.regenerateUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ _token: this.regenerateTokenValue }),
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            this.#applyShareCode(data.shareCode);
            showSuccessToast(this.regenerateMessageValue);
        } catch {
            // Échec silencieux volontaire — même pattern que dashboard_widgets_controller.js.
        }
    }

    async copy() {
        try {
            await navigator.clipboard.writeText(this.codeTarget.textContent.trim());
            showSuccessToast(this.copyMessageValue);
        } catch {
            // Échec silencieux volontaire — l'utilisateur peut toujours copier le texte à la main.
        }
    }

    // Le code, une fois généré, reste affiché même si le partage est ensuite désactivé
    // (shareCode n'est jamais effacé côté serveur) — jamais de ré-masquage ici.
    #applyShareCode(shareCode) {
        if (! shareCode) {
            return;
        }

        if (this.hasCodeTarget) {
            this.codeTarget.textContent = shareCode;
        }

        if (this.hasCodeWrapperTarget) {
            this.codeWrapperTarget.hidden = false;
        }
    }
}
