import { Controller } from '@hotwired/stimulus';
import { showSuccessToast, showErrorToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['code', 'fullCode', 'codeWrapper'];

    static values = {
        toggleUrl: String,
        regenerateUrl: String,
        toggleToken: String,
        regenerateToken: String,
        enableMessage: String,
        disableMessage: String,
        regenerateMessage: String,
        copyMessage: String,
        copyErrorMessage: String,
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
            window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
                detail: { isDiscoverable: data.isDiscoverable },
            }));
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
        const text = this.fullCodeTarget.textContent.trim();

        if (await this.#writeToClipboard(text)) {
            showSuccessToast(this.copyMessageValue);
        } else {
            showErrorToast(this.copyErrorMessageValue);
        }
    }

    // Observé en pratique : navigator.clipboard.writeText() peut résoudre sans lever d'erreur
    // tout en n'écrivant rien (permission accordée de façon incohérente selon le contexte) —
    // execCommand('copy') sur un textarea temporaire, synchrone et exécuté dans le même geste
    // utilisateur que le clic, est essayé en premier car plus fiable ici ; la Clipboard API
    // moderne ne sert que de repli si execCommand est indisponible.
    async #writeToClipboard(text) {
        if (this.#legacyCopy(text)) {
            return true;
        }

        if (navigator.clipboard?.writeText) {
            try {
                await navigator.clipboard.writeText(text);

                return true;
            } catch {
                return false;
            }
        }

        return false;
    }

    #legacyCopy(text) {
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.focus();
        textarea.select();

        let succeeded = false;

        try {
            succeeded = document.execCommand('copy');
        } catch {
            succeeded = false;
        }

        document.body.removeChild(textarea);

        return succeeded;
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
