import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

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

    static STATES = ['none', 'primary', 'secondary'];

    static COLORS = {
        none:      { fill: '#1e293b',               stroke: 'rgba(255,255,255,0.1)', color: '#1e293b'               },
        primary:   { fill: 'rgba(244,63,94,0.55)',  stroke: '#f43f5e',              color: 'rgba(244,63,94,0.55)'  },
        secondary: { fill: 'rgba(249,115,22,0.45)', stroke: '#f97316',              color: 'rgba(249,115,22,0.45)' },
    };

    /** @type {Map<string, 'none'|'primary'|'secondary'>} */
    #states = new Map();

    connect() {
        this.pillTargets.forEach(pill => {
            this.#states.set(pill.dataset.muscleId, 'none');
        });

        this.#applyColors();
    }

    // ─── Public actions ────────────────────────────────────────────────────────

    cyclePill(event) {
        const pill     = event.currentTarget;
        const muscleId = pill.dataset.muscleId;
        const next     = this.#nextState(this.#states.get(muscleId));

        this.#states.set(muscleId, next);

        this.#updatePill(pill, next);
        this.#applyColors();
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
        const muscleValid = this.#hasPrimaryMuscle();

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

    #hasPrimaryMuscle() {
        for (const [, state] of this.#states) {
            if (state === 'primary') return true;
        }

        return false;
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

    #applyColors() {
        const container = this.element;
        const none      = this.constructor.COLORS.none;

        container.querySelectorAll('g.bodymap').forEach(g => {
            g.style.fill   = none.fill;
            g.style.stroke = none.stroke;
            g.style.color  = none.color;
        });

        this.#states.forEach((state, muscleId) => {
            if (state === 'none') return;

            const pill   = this.pillTargets.find(p => p.dataset.muscleId === muscleId);
            const svgIds = JSON.parse(pill?.dataset.svgIds ?? '[]');

            svgIds.forEach(id => this.#colorGroup(container, id, state));
        });
    }

    /**
     * @param {HTMLElement} container
     * @param {string} id
     * @param {'primary'|'secondary'} state
     */
    #colorGroup(container, id, state) {
        const { fill, stroke, color } = this.constructor.COLORS[state];

        container.querySelectorAll(`#${id}`).forEach(el => {
            el.style.fill   = fill;
            el.style.stroke = stroke;
            el.style.color  = color;
        });
    }

    /**
     * @param {'none'|'primary'|'secondary'} current
     * @returns {'none'|'primary'|'secondary'}
     */
    #nextState(current) {
        const states = this.constructor.STATES;
        const idx    = states.indexOf(current ?? 'none');

        return states[(idx + 1) % states.length];
    }

    /**
     * @param {HTMLElement} pill
     * @param {'none'|'primary'|'secondary'} state
     */
    #updatePill(pill, state) {
        pill.dataset.state = state;
        pill.classList.remove('pill--primary', 'pill--secondary');

        if (state !== 'none') {
            pill.classList.add(`pill--${state}`);
        }

        this.#updatePillDot(pill, state);
        this.#updatePillBadge(pill, state);
    }

    /** @param {HTMLElement} pill */
    #updatePillDot(pill, state) {
        const dot = pill.querySelector('[data-role="dot"]');

        if (!dot) return;

        dot.hidden = state === 'none';
    }

    /** @param {HTMLElement} pill */
    #updatePillBadge(pill, state) {
        const badge = pill.querySelector('[data-role="badge"]');

        if (!badge) return;

        badge.hidden      = state === 'none';
        badge.textContent = state === 'primary' ? 'P' : 'S';
    }

    #updateRecap() {
        this.recapPrimaryTarget.innerHTML   = this.#buildRecapHtml(this.#getMusclesByState('primary'), 'primary');
        this.recapSecondaryTarget.innerHTML = this.#buildRecapHtml(this.#getMusclesByState('secondary'), 'secondary');
    }

    /**
     * @param {'primary'|'secondary'} state
     * @returns {Array<{id: string, label: string}>}
     */
    #getMusclesByState(state) {
        return this.pillTargets
            .filter(pill => this.#states.get(pill.dataset.muscleId) === state)
            .map(pill => ({ id: pill.dataset.muscleId, label: pill.dataset.muscleLabel }));
    }

    /**
     * @param {Array<{label: string}>} muscles
     * @param {'primary'|'secondary'} type
     * @returns {string}
     */
    #buildRecapHtml(muscles, type) {
        if (muscles.length === 0) {
            return `<span class="text-[12px] text-[#334155] italic">${this.labelNoneValue}</span>`;
        }

        return muscles
            .map(m => `<span class="recap-pill recap-pill--${type}">${m.label}</span>`)
            .join('');
    }

    #syncHiddenInput() {
        const assignments = [];

        this.#states.forEach((state, muscleId) => {
            if (state === 'none') return;

            assignments.push({ id: muscleId, type: state });
        });

        this.musclesInputTarget.value = JSON.stringify(assignments);
    }
}
