// assets/controllers/routine/restore_controller.js

import { Controller } from '@hotwired/stimulus';
import { confirmAction, sendActionRequest, showDeleteError } from '../../utils/delete_confirmation.js';
import { showSuccessToast } from '../../utils/toast.js';

const HTTP_CONFLICT = 409;
const RELOAD_DELAY_MS = 1200;

export default class extends Controller {
    static values = {
        url:              String,
        csrfToken:        String,
        confirmTitle:     String,
        confirmText:      String,
        confirmButton:    String,
        cancelButton:     String,
        errorText:        String,
        nameConflictText: String,
    };

    async confirmRestore() {
        const confirmed = await confirmAction({
            icon:              'question',
            title:             this.confirmTitleValue,
            text:              this.confirmTextValue,
            confirmButtonText: this.confirmButtonValue,
            cancelButtonText:  this.cancelButtonValue,
        });

        if (!confirmed) { return; }

        const { ok, status, data } = await sendActionRequest(this.urlValue, 'POST', this.csrfTokenValue);

        if (!ok || !data?.success) {
            showDeleteError({ title: this.#errorMessageFor(status) });
            return;
        }

        showSuccessToast(data.message);
        // Les listes, l'onglet et son compteur sont rendus par le serveur.
        setTimeout(() => window.location.reload(), RELOAD_DELAY_MS);
    }

    #errorMessageFor(status) {
        return HTTP_CONFLICT === status ? this.nameConflictTextValue : this.errorTextValue;
    }
}
