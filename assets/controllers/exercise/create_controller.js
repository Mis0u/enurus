import { Controller } from '@hotwired/stimulus';
import { MusclePills, updatePillVisual, renderMuscleRecap, syncMusclesInput, showFieldError, showDuplicateAlert } from './muscle_pills.js';

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
        'bodyweightSection',
        'bodyweightToggle',
        'bodyweightRangeWrapper',
        'bodyweightRange',
        'bodyweightPercentDisplay',
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
            showFieldError(this.nameErrorTarget);

            return;
        }

        showFieldError(this.musclesErrorTarget);
    }

    toggleBodyweightVisibility(event) {
        if (!this.hasBodyweightSectionTarget) return;

        const isWeightReps = event.currentTarget.value === 'weight_reps';

        this.bodyweightSectionTarget.hidden = !isWeightReps;

        if (!isWeightReps && this.hasBodyweightRangeTarget) {
            this.bodyweightRangeTarget.disabled = true;
        }
    }

    toggleBodyweightRange() {
        if (!this.hasBodyweightToggleTarget || !this.hasBodyweightRangeTarget) return;

        const isChecked = this.bodyweightToggleTarget.checked;

        this.bodyweightRangeWrapperTarget.hidden = !isChecked;
        this.bodyweightRangeTarget.disabled = !isChecked;
    }

    updateBodyweightPercentDisplay() {
        if (!this.hasBodyweightRangeTarget || !this.hasBodyweightPercentDisplayTarget) return;

        this.bodyweightPercentDisplayTarget.textContent = `${this.bodyweightRangeTarget.value}%`;
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
            showDuplicateAlert(
                this.duplicateCustomMessageValue
                    .replace('%name%', data.name)
                    .replace('%date%', data.date),
                { customClass: { popup: 'rounded-xl' } },
            );

            return;
        }

        if (data.type === 'public') {
            showDuplicateAlert(
                this.duplicatePublicMessageValue.replace('%name%', data.name),
                { customClass: { popup: 'rounded-xl' } },
            );
        }
    }

    #validateName() {
        return this.hasNameInputTarget && this.nameInputTarget.value.trim().length >= 2;
    }

    /**
     * @param {HTMLElement} target
     * @param {boolean} visible
     */
    #toggleError(target, visible) {
        if (target) target.hidden = !visible;
    }

    #updateRecap() {
        renderMuscleRecap(this.#pills, this.recapPrimaryTarget, this.recapSecondaryTarget, this.labelNoneValue);
    }

    #syncHiddenInput() {
        syncMusclesInput(this.#pills, this.musclesInputTarget);
    }
}
