// assets/controllers/routine/routine-dom-builder.js
//
// Responsabilité unique : construire les éléments DOM injectés dynamiquement.
// Aucune logique métier, aucun accès au controller.

import { SVG } from './routine-constants.js';

/**
 * Construit le div d'un exercice sélectionné dans la colonne droite.
 *
 * @param {string} id
 * @param {{ name: string, primaryMuscles: string[], secondaryMuscles: string[] }} data
 * @param {number} position
 * @param {string} controllerName
 * @param {function(string): string} escape
 * @returns {HTMLDivElement}
 */
export function buildSelectedItem(id, data, position, controllerName, escape) {
    const div = document.createElement('div');
    div.className          = 'routine-selected-item-enter flex items-center gap-2.5 bg-[#080e1a] border border-white/[0.07] rounded-[10px] px-3.5 py-[11px] transition-colors duration-200 hover:border-white/[0.12]';
    div.dataset.exerciseId = id;

    div.innerHTML = `
        <div class="routine-drag-handle cursor-grab text-[#4a5568] p-0.5 flex-shrink-0 hover:text-[#8b9bb4] active:cursor-grabbing">${SVG.drag}</div>
        <span
            class="font-rajdhani text-[13px] font-bold text-rose-500/50 w-5 text-center flex-shrink-0"
            data-${controllerName}-target="itemPosition"
        >#${position}</span>
        <div class="flex-1 min-w-0">
            <p class="text-[13.5px] font-medium text-[#f0f4ff] truncate mb-[3px]">${escape(data.name)}</p>
            <div class="flex gap-1 flex-wrap">
                ${buildMuscleTags(data.primaryMuscles, 'primary', escape)}
                ${buildMuscleTags(data.secondaryMuscles, 'secondary', escape)}
            </div>
        </div>
        <button
            type="button"
            class="w-[26px] h-[26px] rounded-[6px] border border-transparent bg-transparent text-[#4a5568] flex items-center justify-center flex-shrink-0 cursor-pointer transition-all duration-200 hover:bg-rose-500/[0.12] hover:border-rose-500/30 hover:text-rose-500"
            data-action="click->${controllerName}#removeExercise"
            data-exercise-id="${id}"
        >${SVG.cross}</button>
    `;

    return div;
}

/**
 * Construit un élément d'erreur inline.
 *
 * @param {string} message
 * @returns {HTMLDivElement}
 */
export function buildErrorElement(message) {
    const el     = document.createElement('div');
    el.className = 'field-error flex items-center gap-2 px-3 py-2.5 mt-2 rounded-[8px] bg-rose-500/[0.08] border border-rose-500/30';
    el.innerHTML = `${SVG.warn}<span class="text-[12px] font-medium text-rose-400">${message}</span>`;
    return el;
}

/**
 * @param {string[]} muscles
 * @param {'primary'|'secondary'} type
 * @param {function(string): string} escape
 * @returns {string}
 */
function buildMuscleTags(muscles, type, escape) {
    const classes = type === 'primary'
        ? 'bg-rose-500/[0.10] border border-rose-500/20 text-rose-400'
        : 'bg-orange-500/[0.10] border border-orange-500/20 text-orange-400';

    return muscles.map(m =>
        `<span data-muscle-type="${type}" class="inline-flex px-[7px] py-[2px] rounded-[5px] text-[10.5px] font-medium ${classes}">${escape(m)}</span>`
    ).join('');
}
