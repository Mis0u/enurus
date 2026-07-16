// assets/controllers/routine/delete_controller.js

import { Controller } from '@hotwired/stimulus';
import { confirmDeletion, sendDeleteRequest, showDeleteError } from '../../utils/delete_confirmation.js';
import { showSuccessToast } from '../../utils/toast.js';

export default class extends Controller {
    static values = {
        url:           String,
        csrfToken:     String,
        name:          String,
        confirmTitle:  String,
        confirmText:   String,
        confirmButton: String,
        cancelButton:  String,
        errorText:     String,
    };

    async confirmDelete() {
        const confirmed = await confirmDeletion({
            title:             this.confirmTitleValue,
            text:              this.confirmTextValue,
            confirmButtonText: this.confirmButtonValue,
            cancelButtonText:  this.cancelButtonValue,
        });

        if (!confirmed) { return; }

        const { ok, data } = await sendDeleteRequest(this.urlValue, this.csrfTokenValue);

        if (!ok || !data?.success) {
            showDeleteError({ title: this.errorTextValue });
            return;
        }

        showSuccessToast(data.message);
        this.#removeCard();
    }

    /**
     * Retire la card du DOM, puis recharge la page si c'était la dernière.
     * Recharger est le choix le plus simple et fiable ici : reconstruire
     * l'état vide + le compteur en JS dupliquerait la logique serveur.
     */
    #removeCard() {
        const grid = document.querySelector('.routines-grid');
        const card = this.element.closest('.routine-card');
        card?.remove();
        const remainingCards = grid?.querySelectorAll('.routine-card').length ?? 0;
        if (remainingCards === 0) {
            setTimeout(() => window.location.reload(), 1200);
            return;
        }
        this.#decrementCounter();
    }

    #decrementCounter() {
        const counter = document.querySelector('[data-routine-count]');
        if (!counter) { return; }

        const current = parseInt(counter.dataset.routineCount, 10);
        const next    = Math.max(0, current - 1);

        counter.dataset.routineCount = String(next);
        counter.textContent          = this.#formatCount(next);
    }

    #formatCount(count) {
        // Fallback simple — le pluriel ICU complet reste géré côté serveur
        // au prochain chargement complet de la page.
        return count === 0
            ? '0 routine'
            : `${count} routine${count > 1 ? 's' : ''}`;
    }
}
