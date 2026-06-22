import { Controller } from '@hotwired/stimulus';
import Swal from 'sweetalert2';

const CYCLE = ['none', 'primary', 'secondary'];

const COLORS = {
    none:      { fill: '#1e293b',               stroke: 'rgba(255,255,255,0.1)' },
    primary:   { fill: 'rgba(244,63,94,0.55)',  stroke: '#f43f5e'              },
    secondary: { fill: 'rgba(249,115,22,0.45)', stroke: '#f97316'              },
};

/**
 * Exercise edit controller.
 *
 * Identical to exercise--create with one addition:
 * #initFromExistingData() reads the pre-filled hidden input (JSON from DataTransformer)
 * and restores pill states + SVG colors + recap on connect().
 *
 * File: assets/controllers/exercise/edit_controller.js
 */
export default class extends Controller {

    static targets = [
        'nameInput',
        'nameError',
        'musclesInput',
        'musclesError',
        'pill',
        'recapPrimary',
        'recapSecondary',
    ];

    static values = {
        labelNone:               String,
        checkDuplicateUrl:       String,
        duplicateCustomMessage:  String,
        duplicatePublicMessage:  String,
    };

    /** @type {Map<string, string>} muscleId → 'none'|'primary'|'secondary' */
    #states = new Map();

    #abortController = null;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    connect() {
        this.#initStates();
        this.#paintAllSvg('none');
        this.#initFromExistingData();
        this.#updateRecap();
        this.#openAccordionIfDescription();
    }

    disconnect() {
        this.#abortController?.abort();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    cyclePill({ currentTarget: pill }) {
        const id   = pill.dataset.muscleId;
        const next = CYCLE[(CYCLE.indexOf(this.#states.get(id)) + 1) % CYCLE.length];

        this.#states.set(id, next);
        this.#applyPillState(pill, next);
        this.#paintMuscle(pill, next);
        this.#updateRecap();
        this.#syncHiddenInput();
    }

    submitForm(event) {
        const nameOk   = this.#validateName();
        const muscleOk = this.#hasPrimary();

        if (nameOk && muscleOk) {
            return;
        }

        event.preventDefault();

        if (!nameOk) {
            this.#showError(this.nameErrorTarget);
            return;
        }

        this.#showError(this.musclesErrorTarget);
    }

    clearNameError() {
        this.nameErrorTarget.hidden = true;
    }

    async checkDuplicate() {
        const name = this.nameInputTarget.value.trim();

        if (name.length < 2) {
            return;
        }

        this.#abortController?.abort();
        this.#abortController = new AbortController();

        try {
            const url = new URL(this.checkDuplicateUrlValue, window.location.origin);
            url.searchParams.set('name', name);

            const response = await fetch(url.toString(), { signal: this.#abortController.signal });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            this.#handleDuplicateResponse(data, name);
        } catch {
            // Aborted or network error — fail silently
        }
    }

    toggleAccordion() {
        const body   = this.#accordionBody();
        const button = this.#accordionButton();
        const isOpen = body?.style.maxHeight !== '0px' && body?.style.maxHeight !== '';

        if (!body) { return; }

        body.style.maxHeight = isOpen ? '0px' : `${body.scrollHeight}px`;
        button?.setAttribute('aria-expanded', String(!isOpen));
        this.#accordionWrapper()?.classList.toggle('open', !isOpen);
    }

    // ── Private — init ────────────────────────────────────────────────────────

    #initStates() {
        this.pillTargets.forEach(pill => {
            this.#states.set(pill.dataset.muscleId, 'none');
        });
    }

    /**
     * Reads pre-filled JSON from hidden input set by ExerciseMuscleDataTransformer::transform().
     * Expected: [{ id: string, type: 'PRIMARY'|'SECONDARY' }, ...]
     */
    #initFromExistingData() {
        const raw = this.musclesInputTarget.value.trim();

        if (!raw) {
            return;
        }

        let items;

        try {
            items = JSON.parse(raw);
        } catch {
            return;
        }

        if (!Array.isArray(items)) {
            return;
        }

        items.forEach(({ id, type }) => {
            const state = type?.toLowerCase();

            if (!state || state === 'none') {
                return;
            }

            const pill = this.pillTargets.find(p => p.dataset.muscleId === id);

            if (!pill) {
                return;
            }

            this.#states.set(id, state);
            this.#applyPillState(pill, state);
            this.#paintMuscle(pill, state);
        });
    }

    #openAccordionIfDescription() {
        const body    = this.#accordionBody();
        const button  = this.#accordionButton();
        const textarea = body?.querySelector('textarea');

        if (!textarea?.value.trim() || !body) {
            return;
        }

        body.style.maxHeight = `${body.scrollHeight}px`;
        button?.setAttribute('aria-expanded', 'true');
        this.#accordionWrapper()?.classList.add('open');
    }

    // ── Private — accordion helpers ───────────────────────────────────────────

    #accordionWrapper() {
        return this.element.querySelector('#description-accordion');
    }

    #accordionBody() {
        return this.element.querySelector('#description-body');
    }

    #accordionButton() {
        return this.element.querySelector('#description-accordion button');
    }

    // ── Private — pills ───────────────────────────────────────────────────────

    #applyPillState(pill, state) {
        pill.dataset.state = state;
        pill.classList.remove('pill--primary', 'pill--secondary');

        if (state !== 'none') {
            pill.classList.add(`pill--${state}`);
        }

        const dot   = pill.querySelector('[data-role="dot"]');
        const badge = pill.querySelector('[data-role="badge"]');

        if (dot)   { dot.hidden = state === 'none'; }
        if (badge) {
            badge.hidden      = state === 'none';
            badge.textContent = state === 'primary' ? 'P' : state === 'secondary' ? 'S' : '';
        }
    }

    // ── Private — SVG ─────────────────────────────────────────────────────────

    #paintAllSvg(state) {
        this.element.querySelectorAll('g.bodymap').forEach(g => this.#paintGroup(g, state));
    }

    #paintMuscle(pill, state) {
        const ids = JSON.parse(pill.dataset.svgIds ?? '[]');

        ids.forEach(id => {
            const group = this.element.querySelector(`g#${id}`);
            if (group) { this.#paintGroup(group, state); }
        });
    }

    #paintGroup(group, state) {
        const colors = COLORS[state] ?? COLORS.none;
        group.style.fill   = colors.fill;
        group.style.stroke = colors.stroke;
        group.style.color  = colors.fill;
    }

    // ── Private — recap ───────────────────────────────────────────────────────

    #updateRecap() {
        const primary   = [];
        const secondary = [];

        this.#states.forEach((state, id) => {
            const pill  = this.pillTargets.find(p => p.dataset.muscleId === id);
            const label = pill?.dataset.muscleLabel ?? id;

            if (state === 'primary')   { primary.push(label); }
            if (state === 'secondary') { secondary.push(label); }
        });

        this.recapPrimaryTarget.innerHTML   = this.#buildRecapHtml(primary,   'primary');
        this.recapSecondaryTarget.innerHTML = this.#buildRecapHtml(secondary, 'secondary');
    }

    #buildRecapHtml(labels, type) {
        if (labels.length === 0) {
            return `<span class="text-[12px] text-[#334155] italic">${this.labelNoneValue}</span>`;
        }

        return labels
            .map(label => `<span class="recap-pill recap-pill--${type}">${label}</span>`)
            .join('');
    }

    // ── Private — hidden input ────────────────────────────────────────────────

    #syncHiddenInput() {
        const data = [];

        this.#states.forEach((state, id) => {
            if (state !== 'none') {
                data.push({ id, type: state });
            }
        });

        this.musclesInputTarget.value = JSON.stringify(data);
    }

    // ── Private — duplicate check ─────────────────────────────────────────────

    #handleDuplicateResponse(data, name) {
        const originalName = this.nameInputTarget.dataset.originalName ?? '';

        if (data.type === 'custom') {
            if (data.name?.toLowerCase() === originalName.toLowerCase()) {
                return;
            }

            this.#showDuplicateAlert(
                this.duplicateCustomMessageValue
                    .replace('%name%', data.name ?? name)
                    .replace('%date%', data.date ?? '')
            );
            return;
        }

        if (data.type === 'public') {
            this.#showDuplicateAlert(
                this.duplicatePublicMessageValue.replace('%name%', data.name ?? name)
            );
        }
    }

    #showDuplicateAlert(message) {
        if (typeof Swal === 'undefined') { return; }

        Swal.fire({
            icon:               'info',
            text:               message,
            confirmButtonText:  'OK',
            background:         '#111827',
            color:              '#f1f5f9',
            confirmButtonColor: '#f43f5e',
        });
    }

    // ── Private — validation ──────────────────────────────────────────────────

    #validateName() {
        return this.nameInputTarget.value.trim().length >= 2;
    }

    #hasPrimary() {
        for (const state of this.#states.values()) {
            if (state === 'primary') { return true; }
        }
        return false;
    }

    #showError(target) {
        target.hidden = false;
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}
