import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';
import { confirmDeletion, sendDeleteRequest, showDeleteError } from '../../../utils/delete_confirmation.js';

export default class extends Controller {

    async deleteWorkout(event) {
        await this.#confirmAndDelete(event);
    }

    /**
     * Même flux que deleteWorkout() — un deload est une entité distincte (`DeloadPeriod`) mais la
     * confirmation/suppression/reload sont identiques, seule l'URL/le token diffèrent (déjà
     * portés par les data-attributes du bouton).
     */
    async deleteDeloadPeriod(event) {
        await this.#confirmAndDelete(event);
    }

    // ─── Privé ───────────────────────────────────────────────────

    async #confirmAndDelete(event) {
        event.stopPropagation();

        const btn = event.currentTarget;

        const confirmed = await confirmDeletion({
            title:             btn.dataset.title,
            text:              btn.dataset.message,
            confirmButtonText: btn.dataset.confirm,
            cancelButtonText:  btn.dataset.cancel,
            reverseButtons:    true,
            customClass: {
                cancelButton: 'swal-cancel-btn',
                popup:        'swal-enurus-popup',
            },
        });

        if (!confirmed) {
            return;
        }

        const { ok, data } = await sendDeleteRequest(btn.dataset.deleteUrl, btn.dataset.token);

        if (!ok || !data?.success) {
            showDeleteError({ title: btn.dataset.errorText });
            return;
        }

        Swal.fire({
            toast:             true,
            position:           'top-end',
            icon:               'success',
            title:              data.message,
            showConfirmButton:  false,
            timer:              1200,
            timerProgressBar:   true,
            background:         '#0f1928',
            color:              '#f0f4ff',
            iconColor:          '#22c55e',
        });
        setTimeout(() => window.location.reload(), 1200);
    }
}
