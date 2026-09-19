import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static values = {
        url: String,
        csrfToken: String,
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
                showSuccessToast(this.successMessageValue);
            }
        } catch {
            // Échec silencieux volontaire — la case garde son état, l'absence de toast signale
            // l'échec à l'utilisateur (même pattern que select_field_controller.js).
        }
    }
}
