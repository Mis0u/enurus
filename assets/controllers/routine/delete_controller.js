// assets/controllers/routine/delete_controller.js

import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

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
        const confirmed = await this.#showConfirmation();
        if (!confirmed) { return; }
        await this.#sendDeleteRequest();
    }

    async #showConfirmation() {
        const result = await Swal.fire({
            title:              this.confirmTitleValue,
            text:               this.confirmTextValue,
            icon:               'warning',
            showCancelButton:   true,
            confirmButtonText:  this.confirmButtonValue,
            cancelButtonText:   this.cancelButtonValue,
            confirmButtonColor: '#f43f5e',
            cancelButtonColor:  '#334155',
            background:         '#0f1928',
            color:              '#f0f4ff',
        });

        return result.isConfirmed;
    }

    async #sendDeleteRequest() {
        let response;
        try {
            response = await fetch(this.urlValue, {
                method:  'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token':     this.csrfTokenValue,
                },
            });
        } catch {
            this.#showError();
            return;
        }
        if (!response.ok) {
            this.#showError();
            return;
        }
        const data = await response.json();
        if (data.success) {
            this.#showSuccessToast(data.message);
            this.#removeCard();
        } else {
            this.#showError();
        }
    }

    #showSuccessToast(message) {
        Swal.fire({
            toast:              true,
            position:            'top-end',
            icon:                'success',
            title:               message,
            showConfirmButton:   false,
            timer:               3000,
            timerProgressBar:    true,
            background:          '#0f1928',
            color:               '#f0f4ff',
            iconColor:           '#22c55e',
        });
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

    #showError() {
        Swal.fire({
            title:              this.errorTextValue,
            icon:               'error',
            background:         '#0f1928',
            color:              '#f0f4ff',
            confirmButtonColor: '#f43f5e',
        });
    }
}
