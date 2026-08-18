// assets/controllers/workout/routine-loader_controller.js
//
// Charge les exercices d'une routine sélectionnée dans la liste des
// exercices de la séance. Si des exercices sont déjà présents, demande
// confirmation avant de tout remplacer (SweetAlert2). Affiche un loader
// Tailwind pendant le fetch.

import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

export default class extends Controller {
    static targets = ['select'];

    static values = {
        blockUrl:      String,
        warningTitle:  String,
        warningText:   String,
        confirmButton: String,
        cancelButton:  String,
    };

    #previousValue = '';
    #loaderEl      = null;

    connect() {
        this.#previousValue = this.selectTarget.value;
    }

    async onChange() {
        const routineId = this.selectTarget.value;

        const exerciseList = document.getElementById('exercises-list');
        const hasExercises = exerciseList?.children.length > 0;

        if (hasExercises) {
            const confirmed = await this.#showWarning();

            if (!confirmed) {
                this.selectTarget.value = this.#previousValue;
                return;
            }
        }

        if ('' === routineId) {
            if (exerciseList) {
                exerciseList.innerHTML = '';
            }
            this.#previousValue = routineId;
            return;
        }

        await this.#loadRoutineExercises(routineId, exerciseList);
        this.#previousValue = routineId;
    }

    async #showWarning() {
        const result = await Swal.fire({
            title:              this.warningTitleValue,
            text:               this.warningTextValue,
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

    async #loadRoutineExercises(routineId, exerciseList) {
        if (!exerciseList) { return; }

        this.#showLoader();
        exerciseList.innerHTML = '';

        const url = new URL(this.blockUrlValue, window.location.origin);
        url.searchParams.set('routineId', routineId);
        url.searchParams.set('startIndex', '0');

        let response;

        try {
            response = await fetch(url.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
        } catch {
            this.#hideLoader();
            return;
        }

        if (!response.ok) {
            this.#hideLoader();
            return;
        }

        const html = await response.text();
        exerciseList.insertAdjacentHTML('beforeend', html);
        window.dispatchEvent(new CustomEvent('routine:exercises-loaded', { detail: { exerciseList } }));
        this.#hideLoader();
    }

    // -------------------------------------------------------------------------
    // Loader visuel — Tailwind pur (animate-spin natif, pas de CSS custom)
    // -------------------------------------------------------------------------

    #showLoader() {
        this.selectTarget.disabled = true;
        this.selectTarget.classList.add('opacity-50', 'cursor-wait');

        this.#loaderEl = document.createElement('span');
        this.#loaderEl.className = 'inline-block w-4 h-4 ml-2 align-middle border-2 border-rose-500/20 border-t-rose-500 rounded-full animate-spin';
        this.#loaderEl.setAttribute('aria-hidden', 'true');
        this.selectTarget.insertAdjacentElement('afterend', this.#loaderEl);
    }

    #hideLoader() {
        this.selectTarget.disabled = false;
        this.selectTarget.classList.remove('opacity-50', 'cursor-wait');
        this.#loaderEl?.remove();
        this.#loaderEl = null;
    }
}
