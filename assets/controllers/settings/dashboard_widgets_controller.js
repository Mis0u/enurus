import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static values = {
        url: String,
        shareUrl: String,
        csrfToken: String,
        shareCsrfToken: String,
        successMessage: String,
    };

    async toggle(event) {
        const widget = event.params.widget;
        const hidden = !event.target.checked;

        try {
            const response = await fetch(this.urlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ widget, hidden, _token: this.csrfTokenValue }),
            });

            if (response.ok) {
                this.#syncShareCheckboxAvailability(event.target, hidden);
                showSuccessToast(this.successMessageValue);
            }
        } catch {
            // Échec silencieux volontaire — la case garde son état, l'absence de toast signale
            // l'échec à l'utilisateur (même pattern que select_field_controller.js).
        }
    }

    async toggleShare(event) {
        const widget = event.params.widget;
        const hiddenForShare = event.target.checked;

        try {
            const response = await fetch(this.shareUrlValue, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ widget, hiddenForShare, _token: this.shareCsrfTokenValue }),
            });

            if (response.ok) {
                showSuccessToast(this.successMessageValue);
            }
        } catch {
            // Échec silencieux volontaire — même pattern que toggle().
        }
    }

    // La case "masquer pour mes connexions" n'a de sens que si le widget est encore visible sur
    // son propre dashboard — quand `toggle()` le masque, elle se désactive dans le même geste.
    #syncShareCheckboxAvailability(mainCheckbox, hidden) {
        const row = mainCheckbox.closest('[data-widget-key]');
        const shareCheckbox = row?.querySelector('input[data-action*="toggleShare"]');

        if (shareCheckbox) {
            shareCheckbox.disabled = hidden;
        }
    }
}
