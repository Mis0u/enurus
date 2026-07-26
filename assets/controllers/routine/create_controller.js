// assets/controllers/routine/create_controller.js

import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { CSS, SVG } from './routine-constants.js';
import { buildSelectedItem, buildErrorElement } from './routine-dom-builder.js';
import { initNameChecker } from './routine-name-checker.js';
import { matchesFilters, normalizeForSearch } from './routine-exercise-filter.js';
import { MusclePills, updatePillVisual } from '../exercise/muscle_pills.js';
import { paintMuscleGroupsByIds, resetBodymap } from '../../utils/muscle_colors.js';

export default class extends Controller {
    static targets = [
        'nameInput',
        'exercisesInput',
        'searchInput',
        'exerciseItem',
        'selectedList',
        'emptyState',
        'searchEmptyState',
        'summaryName',
        'summaryCount',
        'submitBtn',
        'accordionBody',
        'musclePreviewToggle',
        'musclePreviewBody',
        'muscleBody',
        'muscleFilterChip',
        'muscleFilterCount',
    ];

    static values = {
        errorName:       String,
        errorExercise:   String,
        errorNameExists: String,
        checkNameUrl:    String,
        labelSingular:   String,
        labelPlural:     String,
    };

    #exerciseData             = new Map();
    #selectedIds              = [];
    #searchQuery              = '';
    #sortable                 = null;
    #nameIsAvailable          = true;
    #musclePreviewAutoOpened  = false;

    /** @type {MusclePills} */
    #muscleFilterPills;

    connect() {
        this.#buildExerciseDataMap();
        this.#muscleFilterPills = new MusclePills(this.element, this.muscleFilterChipTargets);
        this.#initSortable();
        this.#updateUI();
        initNameChecker(this);
    }

    disconnect() {
        this.#sortable?.destroy();
        this.#sortable = null;
    }

    // -------------------------------------------------------------------------
    // API publique pour le name checker
    // -------------------------------------------------------------------------

    setNameAvailable(available) {
        this.#nameIsAvailable = available;
        this.#updateUI();
    }

    // -------------------------------------------------------------------------
    // INIT
    // -------------------------------------------------------------------------

    #buildExerciseDataMap() {
        this.exerciseItemTargets.forEach(item => {
            this.#exerciseData.set(item.dataset.exerciseId, {
                name:                  item.dataset.exerciseName ?? '',
                normalizedName:        normalizeForSearch(item.dataset.exerciseName ?? ''),
                primaryMuscleIds:      (item.dataset.muscleIds ?? '').split(',').filter(Boolean),
                secondaryMuscleIds:    (item.dataset.secondaryMuscleIds ?? '').split(',').filter(Boolean),
                primaryMuscleGroupIds: (item.dataset.primaryMuscleGroupIds ?? '').split(',').filter(Boolean),
                secondaryMuscleGroupIds: (item.dataset.secondaryMuscleGroupIds ?? '').split(',').filter(Boolean),
                primaryMuscles:        this.#readMuscleTags(item, 'primary'),
                secondaryMuscles:      this.#readMuscleTags(item, 'secondary'),
            });
        });
    }

    #readMuscleTags(item, type) {
        return Array.from(item.querySelectorAll(`[data-muscle-type="${type}"]`))
            .map(s => s.textContent.trim());
    }

    #initSortable() {
        this.#sortable = new Sortable(this.selectedListTarget, {
            animation:  150,
            handle:     '.routine-drag-handle',
            ghostClass: 'sortable-ghost',
            dragClass:  'sortable-drag',
            onEnd:      () => this.#onSortEnd(),
        });
    }

    // -------------------------------------------------------------------------
    // TOGGLE exercice (colonne gauche)
    // -------------------------------------------------------------------------

    toggleExercise(event) {
        const btn  = event.currentTarget;
        const id   = btn.dataset.exerciseId;
        const item = this.exerciseItemTargets.find(el => el.dataset.exerciseId === id);

        if (!item) { return; }

        if (this.#selectedIds.includes(id)) {
            this.#removeById(id, item, btn);
        } else {
            this.#addById(id, item, btn);
        }

        this.#syncHiddenInput();
        this.#updateUI();
    }

    #addById(id, item, btn) {
        this.#selectedIds.push(id);
        this.#setItemAdded(item, btn);
        this.#appendToSelectedList(id);
        this.#clearExerciseError();
    }

    #removeById(id, item, btn) {
        this.#selectedIds = this.#selectedIds.filter(sid => sid !== id);
        this.#setItemIdle(item, btn);
        this.selectedListTarget.querySelector(`[data-exercise-id="${id}"]`)?.remove();
    }

    // -------------------------------------------------------------------------
    // Retirer depuis colonne droite (bouton croix)
    // -------------------------------------------------------------------------

    removeExercise(event) {
        const id   = event.currentTarget.dataset.exerciseId;
        const item = this.exerciseItemTargets.find(el => el.dataset.exerciseId === id);
        const btn  = item?.querySelector('[data-action*="toggleExercise"]');

        this.#selectedIds = this.#selectedIds.filter(sid => sid !== id);

        if (item && btn) { this.#setItemIdle(item, btn); }

        this.selectedListTarget.querySelector(`[data-exercise-id="${id}"]`)?.remove();

        this.#syncHiddenInput();
        this.#updateUI();
    }

    // -------------------------------------------------------------------------
    // Header dynamique
    // -------------------------------------------------------------------------

    updateHeader() {
        this.#updateSummaryHeader();
    }

    // -------------------------------------------------------------------------
    // Accordéon description
    // -------------------------------------------------------------------------

    toggleAccordion(event) {
        const btn    = event.currentTarget;
        const body   = this.accordionBodyTarget;
        const isOpen = btn.getAttribute('aria-expanded') === 'true';

        body.style.maxHeight = isOpen ? '0' : `${body.scrollHeight}px`;
        btn.setAttribute('aria-expanded', String(!isOpen));
    }

    // -------------------------------------------------------------------------
    // Aperçu silhouette — coloriage muscles primaires/secondaires
    // -------------------------------------------------------------------------

    toggleMusclePreview(event) {
        const isOpen = event.currentTarget.getAttribute('aria-expanded') === 'true';
        this.#setMusclePreviewOpen(!isOpen);
    }

    /**
     * Ouvre le panneau automatiquement une seule fois, au premier exercice ajouté — sert de
     * point d'appel visuel sans que l'utilisateur ait à chercher le panneau. N'insiste pas s'il
     * l'a refermé ensuite : le flag ne se réarme jamais dans la même session de page.
     */
    #autoOpenMusclePreviewOnFirstExercise() {
        if (this.#musclePreviewAutoOpened || this.#selectedIds.length === 0) { return; }

        this.#musclePreviewAutoOpened = true;
        this.#setMusclePreviewOpen(true);
    }

    #setMusclePreviewOpen(open) {
        if (!this.hasMusclePreviewToggleTarget || !this.hasMusclePreviewBodyTarget) { return; }

        const body = this.musclePreviewBodyTarget;
        body.style.maxHeight = open ? `${body.scrollHeight}px` : '0';
        this.musclePreviewToggleTarget.setAttribute('aria-expanded', String(open));
        this.musclePreviewToggleTarget.querySelector('svg')?.classList.toggle('rotate-180', open);
    }

    #paintMusclePreview() {
        if (!this.hasMuscleBodyTarget) { return; }

        const primaryIds   = new Set();
        const secondaryIds = new Set();

        this.#selectedIds.forEach(id => {
            const data = this.#exerciseData.get(id);
            data?.primaryMuscleIds.forEach(svgId => primaryIds.add(svgId));
            data?.secondaryMuscleIds.forEach(svgId => secondaryIds.add(svgId));
        });

        const container = this.muscleBodyTarget;
        resetBodymap(container);
        paintMuscleGroupsByIds(container, Array.from(primaryIds), 'primary');
        paintMuscleGroupsByIds(container, Array.from(secondaryIds), 'secondary');
    }

    // -------------------------------------------------------------------------
    // Filtrage texte
    // -------------------------------------------------------------------------

    filterExercises(event) {
        this.#searchQuery = normalizeForSearch(event.currentTarget.value.trim());
        this.#applyFilters();
    }

    cycleMuscleFilter(event) {
        const chip     = event.currentTarget;
        const muscleId = chip.dataset.muscleId;
        const next     = this.#muscleFilterPills.cycle(muscleId);

        updatePillVisual(chip, next);
        this.#updateMuscleFilterCount();
        this.#applyFilters();
    }

    #updateMuscleFilterCount() {
        const count = this.#muscleFilterPills.toAssignments().length;

        this.muscleFilterCountTarget.hidden      = count === 0;
        this.muscleFilterCountTarget.textContent = count > 0 ? `(${count})` : '';
    }

    #applyFilters() {
        const muscleFilters = this.#muscleFilterPills.toAssignments();
        const anyVisible    = this.exerciseItemTargets.reduce((visible, item) => {
            const data    = this.#exerciseData.get(item.dataset.exerciseId);
            const matches = matchesFilters(data, this.#searchQuery, muscleFilters);
            item.hidden   = !matches;

            return visible || matches;
        }, false);

        this.searchEmptyStateTarget.hidden = anyVisible;
    }

    // -------------------------------------------------------------------------
    // SortableJS
    // -------------------------------------------------------------------------

    #onSortEnd() {
        const items       = this.selectedListTarget.querySelectorAll(':scope > [data-exercise-id]');
        this.#selectedIds = Array.from(items).map(el => el.dataset.exerciseId);
        this.#renumberPositions();
        this.#syncHiddenInput();
    }

    // -------------------------------------------------------------------------
    // Soumission + validation JS
    // -------------------------------------------------------------------------

    submitForm(event) {
        const nameEmpty  = this.nameInputTarget.value.trim() === '';
        const noExercise = this.#selectedIds.length === 0;

        if (!nameEmpty && !noExercise && this.#nameIsAvailable) { return; }

        event.preventDefault();

        if (nameEmpty)  { this.#showFieldError(this.nameInputTarget, this.errorNameValue); }
        if (noExercise) { this.#showInlineError(this.emptyStateTarget, this.errorExerciseValue); }
    }

    #showFieldError(field, message) {
        this.#clearFieldError(field.parentElement);

        field.classList.add('border-rose-500');
        field.insertAdjacentElement('afterend', buildErrorElement(this.#escape(message)));

        field.addEventListener('input', () => {
            this.#clearFieldError(field.parentElement);
            field.classList.remove('border-rose-500');
        }, { once: true });
    }

    #showInlineError(anchor, message) {
        this.#clearFieldError(anchor.parentElement);
        anchor.classList.add('border-rose-500/50');
        anchor.insertAdjacentElement('afterend', buildErrorElement(this.#escape(message)));
    }

    #clearFieldError(parent) {
        parent.querySelector('.field-error')?.remove();
    }

    #clearExerciseError() {
        this.emptyStateTarget.classList.remove('border-rose-500/50');
        this.#clearFieldError(this.emptyStateTarget.parentElement);
    }

    // -------------------------------------------------------------------------
    // ÉTATS VISUELS — item colonne gauche
    // -------------------------------------------------------------------------

    #setItemAdded(item, btn) {
        this.#toggleItemClasses(item, btn, 'added');
    }

    #setItemIdle(item, btn) {
        this.#toggleItemClasses(item, btn, 'idle');
    }

    #toggleItemClasses(item, btn, state) {
        const from = state === 'added' ? CSS.exercise.idle  : CSS.exercise.added;
        const to   = state === 'added' ? CSS.exercise.added : CSS.exercise.idle;

        from.split(' ').forEach(c => item.classList.remove(c));
        to.split(' ').forEach(c => item.classList.add(c));

        btn.className = `${CSS.btn.base} ${CSS.btn[state]}`;
        btn.innerHTML = state === 'added' ? SVG.check : SVG.plus;
    }

    // -------------------------------------------------------------------------
    // Injection item colonne droite
    // -------------------------------------------------------------------------

    #appendToSelectedList(id) {
        const data     = this.#exerciseData.get(id);
        const position = this.#selectedIds.length;

        this.selectedListTarget.appendChild(
            buildSelectedItem(id, data, position, 'routine--create', str => this.#escape(str))
        );
    }

    // -------------------------------------------------------------------------
    // Numérotation des positions
    // -------------------------------------------------------------------------

    #renumberPositions() {
        this.selectedListTarget
            .querySelectorAll('[data-routine--create-target="itemPosition"]')
            .forEach((el, i) => { el.textContent = `#${i + 1}`; });
    }

    // -------------------------------------------------------------------------
    // Hidden input JSON
    // -------------------------------------------------------------------------

    #syncHiddenInput() {
        const data = this.#selectedIds.map((id, i) => ({ id, position: i + 1 }));
        this.exercisesInputTarget.value = JSON.stringify(data);
    }

    // -------------------------------------------------------------------------
    // UI globale
    // -------------------------------------------------------------------------

    #updateUI() {
        const hasItems = this.#selectedIds.length > 0;

        this.emptyStateTarget.hidden = hasItems;

        if (this.hasSubmitBtnTarget) {
            this.submitBtnTarget.disabled = !hasItems || !this.#nameIsAvailable;
        }

        this.#updateSummaryHeader();
        this.#renumberPositions();
        this.#paintMusclePreview();
        this.#autoOpenMusclePreviewOnFirstExercise();
    }

    #updateSummaryHeader() {
        const count = this.#selectedIds.length;

        this.summaryNameTarget.textContent  = this.nameInputTarget.value.trim();
        this.summaryCountTarget.hidden      = count === 0;
        this.summaryCountTarget.textContent = count > 0 ? this.#formatCount(count) : '';
    }

    #formatCount(count) {
        return `${count} ${count === 1 ? this.labelSingularValue : this.labelPluralValue}`;
    }

    // -------------------------------------------------------------------------
    // XSS
    // -------------------------------------------------------------------------

    #escape(str) {
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }
}
