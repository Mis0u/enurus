import Swal from 'sweetalert2';
import { paintMuscleGroupsByIds, resetBodymap } from '../../utils/muscle_colors.js';

export const MUSCLE_STATES = ['none', 'primary', 'secondary'];

/**
 * État + peinture SVG des pills de groupes musculaires — partagé par exercise--create et
 * exercise--edit, qui n'en diffèrent que par la façon dont l'état initial est renseigné
 * (vide en création, restauré depuis le hidden input en édition).
 */
export class MusclePills {
    /** @type {Map<string, 'none'|'primary'|'secondary'>} */
    #states = new Map();

    #container;

    #pillTargets;

    /**
     * @param {Element} container
     * @param {HTMLElement[]} pillTargets
     */
    constructor(container, pillTargets) {
        this.#container = container;
        this.#pillTargets = pillTargets;

        pillTargets.forEach(pill => {
            this.#states.set(pill.dataset.muscleId, 'none');
        });
    }

    /** @param {string} muscleId */
    get(muscleId) {
        return this.#states.get(muscleId) ?? 'none';
    }

    /**
     * @param {string} muscleId
     * @param {'none'|'primary'|'secondary'} state
     */
    set(muscleId, state) {
        this.#states.set(muscleId, state);
    }

    /**
     * @param {string} muscleId
     * @returns {'none'|'primary'|'secondary'}
     */
    cycle(muscleId) {
        const next = MUSCLE_STATES[(MUSCLE_STATES.indexOf(this.get(muscleId)) + 1) % MUSCLE_STATES.length];

        this.#states.set(muscleId, next);

        return next;
    }

    hasPrimary() {
        for (const state of this.#states.values()) {
            if (state === 'primary') return true;
        }

        return false;
    }

    /** Repeint toute la silhouette SVG selon l'état courant de chaque pill. */
    paintAll() {
        resetBodymap(this.#container);

        this.#states.forEach((state, muscleId) => {
            if (state === 'none') return;

            const pill = this.#pillTargets.find(p => p.dataset.muscleId === muscleId);
            const svgIds = JSON.parse(pill?.dataset.svgIds ?? '[]');

            paintMuscleGroupsByIds(this.#container, svgIds, state);
        });
    }

    /**
     * @param {'primary'|'secondary'} state
     * @returns {Array<{id: string, label: string}>}
     */
    musclesByState(state) {
        return this.#pillTargets
            .filter(pill => this.get(pill.dataset.muscleId) === state)
            .map(pill => ({ id: pill.dataset.muscleId, label: pill.dataset.muscleLabel }));
    }

    /**
     * @returns {Array<{id: string, type: 'primary'|'secondary'}>}
     */
    toAssignments() {
        const assignments = [];

        this.#states.forEach((state, id) => {
            if (state !== 'none') {
                assignments.push({ id, type: state });
            }
        });

        return assignments;
    }
}

/**
 * @param {HTMLElement} pill
 * @param {'none'|'primary'|'secondary'} state
 */
export function updatePillVisual(pill, state) {
    pill.dataset.state = state;
    pill.classList.remove('pill--primary', 'pill--secondary');

    if (state !== 'none') {
        pill.classList.add(`pill--${state}`);
    }

    const dot = pill.querySelector('[data-role="dot"]');
    if (dot) dot.hidden = state === 'none';

    const badge = pill.querySelector('[data-role="badge"]');
    if (badge) {
        badge.hidden = state === 'none';
        badge.textContent = state === 'primary' ? 'P' : state === 'secondary' ? 'S' : '';
    }
}

/**
 * @param {Array<{label: string}>} muscles
 * @param {'primary'|'secondary'} type
 * @param {string} labelNone
 */
export function buildRecapHtml(muscles, type, labelNone) {
    if (muscles.length === 0) {
        return `<span class="text-[12px] text-[#334155] italic">${labelNone}</span>`;
    }

    return muscles
        .map(m => `<span class="recap-pill recap-pill--${type}">${m.label}</span>`)
        .join('');
}

/**
 * Rafraîchit les deux blocs de récap (primaire/secondaire) — partagé par exercise--create et
 * exercise--edit.
 *
 * @param {MusclePills} pills
 * @param {HTMLElement} primaryTarget
 * @param {HTMLElement} secondaryTarget
 * @param {string} labelNone
 */
export function renderMuscleRecap(pills, primaryTarget, secondaryTarget, labelNone) {
    primaryTarget.innerHTML   = buildRecapHtml(pills.musclesByState('primary'), 'primary', labelNone);
    secondaryTarget.innerHTML = buildRecapHtml(pills.musclesByState('secondary'), 'secondary', labelNone);
}

/**
 * Sérialise l'état courant des pills dans le hidden input consommé par
 * ExerciseMuscleDataTransformer côté serveur.
 *
 * @param {MusclePills} pills
 * @param {HTMLInputElement} inputTarget
 */
export function syncMusclesInput(pills, inputTarget) {
    inputTarget.value = JSON.stringify(pills.toAssignments());
}

/**
 * Affiche un message d'erreur de champ et scroll jusqu'à lui.
 *
 * @param {HTMLElement} target
 */
export function showFieldError(target) {
    target.hidden = false;
    target.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

/**
 * Alerte SweetAlert2 de doublon d'exercice — mêmes options entre création et édition,
 * seuls l'icône et le style de popup diffèrent selon le contexte.
 *
 * @param {string} message
 * @param {object} [options]
 */
export function showDuplicateAlert(message, options = {}) {
    Swal.fire({
        icon:               'warning',
        text:               message,
        confirmButtonText:  'OK',
        background:         '#111827',
        color:              '#f1f5f9',
        confirmButtonColor: '#f43f5e',
        ...options,
    });
}
