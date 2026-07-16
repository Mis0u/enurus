import { Controller } from '@hotwired/stimulus';
import { MusclePills, updatePillVisual, renderMuscleRecap, syncMusclesInput, showFieldError, showDuplicateAlert } from './muscle_pills.js';

/**
 * Exercise edit controller.
 *
 * Identique à exercise--create, avec en plus #initFromExistingData() qui restaure l'état des
 * pills depuis le hidden input pré-rempli (JSON produit par ExerciseMuscleDataTransformer).
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

    /** @type {MusclePills} */
    #pills;

    #abortController = null;

    // ── Lifecycle ─────────────────────────────────────────────────────────────

    connect() {
        this.#pills = new MusclePills(this.element, this.pillTargets);
        this.#initFromExistingData();
        this.#pills.paintAll();
        this.#updateRecap();
        this.#openAccordionIfDescription();
    }

    disconnect() {
        this.#abortController?.abort();
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    cyclePill({ currentTarget: pill }) {
        const id   = pill.dataset.muscleId;
        const next = this.#pills.cycle(id);

        updatePillVisual(pill, next);
        this.#pills.paintAll();
        this.#updateRecap();
        this.#syncHiddenInput();
    }

    submitForm(event) {
        const nameOk   = this.#validateName();
        const muscleOk = this.#pills.hasPrimary();

        if (nameOk && muscleOk) {
            return;
        }

        event.preventDefault();

        if (!nameOk) {
            showFieldError(this.nameErrorTarget);
            return;
        }

        showFieldError(this.musclesErrorTarget);
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

            this.#pills.set(id, state);
            updatePillVisual(pill, state);
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

    // ── Private — recap ───────────────────────────────────────────────────────

    #updateRecap() {
        renderMuscleRecap(this.#pills, this.recapPrimaryTarget, this.recapSecondaryTarget, this.labelNoneValue);
    }

    // ── Private — hidden input ────────────────────────────────────────────────

    #syncHiddenInput() {
        syncMusclesInput(this.#pills, this.musclesInputTarget);
    }

    // ── Private — duplicate check ─────────────────────────────────────────────

    #handleDuplicateResponse(data, name) {
        const originalName = this.nameInputTarget.dataset.originalName ?? '';

        if (data.type === 'custom') {
            if (data.name?.toLowerCase() === originalName.toLowerCase()) {
                return;
            }

            showDuplicateAlert(
                this.duplicateCustomMessageValue
                    .replace('%name%', data.name ?? name)
                    .replace('%date%', data.date ?? ''),
                { icon: 'info' },
            );
            return;
        }

        if (data.type === 'public') {
            showDuplicateAlert(
                this.duplicatePublicMessageValue.replace('%name%', data.name ?? name),
                { icon: 'info' },
            );
        }
    }

    // ── Private — validation ──────────────────────────────────────────────────

    #validateName() {
        return this.nameInputTarget.value.trim().length >= 2;
    }
}
