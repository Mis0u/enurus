import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';
import { MusclePills, updatePillVisual, buildRecapHtml } from './muscle_pills.js';

/**
 * Stimulus controller: exercise--create
 * Path: assets/controllers/exercise/create_controller.js
 */
export default class extends Controller {
    static targets = [
        'pill',
        'recapPrimary',
        'recapSecondary',
        'musclesInput',
        'accordion',
        'accordionBody',
        'musclesError',
        'nameInput',
        'nameError',
    ];

    static values = {
        labelNone:              { type: String, default: '' },
        checkDuplicateUrl:      { type: String, default: '' },
        duplicateCustomMessage: { type: String, default: '' },
        duplicatePublicMessage: { type: String, default: '' },
    };

    /** @type {MusclePills} */
    #pills;

    connect() {
        this.#pills = new MusclePills(this.element, this.pillTargets);
        this.#pills.paintAll();
    }

    // ─── Public actions ────────────────────────────────────────────────────────

    cyclePill(event) {
        const pill     = event.currentTarget;
        const muscleId = pill.dataset.muscleId;
        const next     = this.#pills.cycle(muscleId);

        updatePillVisual(pill, next);
        this.#pills.paintAll();
        this.#updateRecap();
        this.#syncHiddenInput();
        this.#toggleError(this.musclesErrorTarget, false);
    }

    clearNameError() {
        this.#toggleError(this.nameErrorTarget, false);
    }

    async checkDuplicate() {
        const name = this.nameInputTarget.value.trim();

        if (name.length < 2) return;

        try {
            const url      = `${this.checkDuplicateUrlValue}?name=${encodeURIComponent(name)}`;
            const response = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });

            if (!response.ok) return;

            const data = await response.json();

            this.#handleDuplicateResult(data);
        } catch {
            // Silently ignore network errors — duplicate check is non-blocking
        }
    }

    submitForm(event) {
        const nameValid   = this.#validateName();
        const muscleValid = this.#pills.hasPrimary();

        if (nameValid && muscleValid) return;

        event.preventDefault();

        if (!nameValid) {
            this.#showError(this.nameErrorTarget);

            return;
        }

        this.#showError(this.musclesErrorTarget);
    }

    toggleAccordion() {
        const isOpen = this.accordionTarget.classList.toggle('open');

        this.accordionBodyTarget.style.maxHeight = isOpen
            ? `${this.accordionBodyTarget.scrollHeight}px`
            : '0';
    }

    // ─── Private helpers ───────────────────────────────────────────────────────

    #handleDuplicateResult(data) {
        if (data.type === 'custom') {
            this.#showDuplicateAlert(
                this.duplicateCustomMessageValue
                    .replace('%name%', data.name)
                    .replace('%date%', data.date)
            );

            return;
        }

        if (data.type === 'public') {
            this.#showDuplicateAlert(
                this.duplicatePublicMessageValue.replace('%name%', data.name)
            );
        }
    }

    /** @param {string} message */
    #showDuplicateAlert(message) {
        Swal.fire({
            icon:               'warning',
            text:               message,
            confirmButtonText:  'OK',
            background:         '#111827',
            color:              '#f1f5f9',
            confirmButtonColor: '#f43f5e',
            customClass:        { popup: 'rounded-xl' },
        });
    }

    #validateName() {
        return this.hasNameInputTarget && this.nameInputTarget.value.trim().length >= 2;
    }

    /** @param {HTMLElement} target */
    #showError(target) {
        this.#toggleError(target, true);
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    /**
     * @param {HTMLElement} target
     * @param {boolean} visible
     */
    #toggleError(target, visible) {
        if (target) target.hidden = !visible;
    }

    #updateRecap() {
        this.recapPrimaryTarget.innerHTML   = buildRecapHtml(this.#pills.musclesByState('primary'), 'primary', this.labelNoneValue);
        this.recapSecondaryTarget.innerHTML = buildRecapHtml(this.#pills.musclesByState('secondary'), 'secondary', this.labelNoneValue);
    }

    #syncHiddenInput() {
        this.musclesInputTarget.value = JSON.stringify(this.#pills.toAssignments());
    }
}
