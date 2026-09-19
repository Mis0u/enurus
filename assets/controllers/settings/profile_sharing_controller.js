import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['code', 'codeWrapper'];

    static values = {
        url: String,
        csrfToken: String,
        enableMessage: String,
        disableMessage: String,
        regenerateMessage: String,
        copyMessage: String,
    };

    async toggle(event) {
        const enabled = event.target.checked;

        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'toggle', enabled, _token: this.csrfTokenValue }),
            });

            if (! response.ok) {
                return;
            }

            const data = await response.json();
            this.#applyShareCode(data.shareCode);

            if (this.hasCodeWrapperTarget) {
                this.codeWrapperTarget.hidden = ! enabled;
            }

            showSuccessToast(enabled ? this.enableMessageValue : this.disableMessageValue);
        } catch {
            // Échec silencieux volontaire — même pattern que dashboard_widgets_controller.js.
        }
    }

    async regenerate() {
        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'regenerate', _token: this.csrfTokenValue }),
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

    #applyShareCode(shareCode) {
        if (this.hasCodeTarget && shareCode) {
            this.codeTarget.textContent = shareCode;
        }
    }
}
