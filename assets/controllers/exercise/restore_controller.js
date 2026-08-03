// assets/controllers/exercise/restore_controller.js

import { Controller } from '@hotwired/stimulus';
import { sendActionRequest, showDeleteError } from '../../utils/delete_confirmation.js';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static values = {
        url:       String,
        csrfToken: String,
        errorText: String,
    };

    /**
     * Pas de confirmation — restaurer est une action bénigne et réversible (il suffit de
     * ré-archiver), contrairement à la suppression.
     */
    async confirmRestore() {
        const { ok, data } = await sendActionRequest(this.urlValue, 'POST', this.csrfTokenValue);

        if (!ok || !data?.success) {
            showDeleteError({ title: this.errorTextValue });
            return;
        }

        showSuccessToast(data.message);
        setTimeout(() => window.location.reload(), 1200);
    }
}
