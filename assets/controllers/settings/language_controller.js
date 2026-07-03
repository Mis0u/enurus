import { Controller } from '@hotwired/stimulus';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static targets = ['select'];
    static values = {
        url: String,
        csrfToken: String,
        successMessage: String,
    };

    async save() {
        try {
            const response = await fetch(this.urlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    locale: this.selectTarget.value,
                    _token: this.csrfTokenValue,
                }),
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            showSuccessToast(this.successMessageValue);

            setTimeout(() => {
                window.location.href = data.redirectUrl;
            }, 500);
        } catch {
            // Échec silencieux — la locale reste inchangée côté serveur.
        }
    }
}
