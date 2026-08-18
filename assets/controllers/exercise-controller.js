import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { numerate } from './workout/create/number_series.js';
import { swalError } from './swal/error/_error.js';
import { handleErrorField } from './workout/_error_form.js';
import { NoteModalManager } from './workout/create/note_modal_manager.js';
import { showErrorToast } from '../utils/toast.js';
import { initLiftedWeights, updateLiftedWeightForInput } from './workout/lifted_weight.js';

export default class extends Controller {
    static targets = ['exerciseList'];

    static values = {
        noDateTitle: String,
        noDateText: String,
        noExerciseTitle: String,
        noExerciseText: String,
        submitFailedTitle: String,
        submitFailedText: String,
        uploadPhotoUrl: String,
        userHasBodyweight: Boolean,
        bodyweightRequiredMessage: String,
        liftedWeightTemplate: String,
    };

    #noteModalManager = null;

    connect() {
        this.boundHandler = this.onExerciseSelected.bind(this);
        window.addEventListener('exercise:selected', this.boundHandler);

        this.boundRoutineHandler = this.onRoutineExercisesLoaded.bind(this);
        window.addEventListener('routine:exercises-loaded', this.boundRoutineHandler);

        this.#noteModalManager = new NoteModalManager(
            this.application,
            this.uploadPhotoUrlValue,
            this.submitFailedTitleValue,
            this.submitFailedTextValue,
        );

        this.sortable = new Sortable(this.exerciseListTarget, {
            animation: 250,
            handle: '.drag-handle',
            ghostClass: 'opacity-30',
            chosenClass: 'opacity-50',
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            onEnd: () => this.#updatePositions(),
        });

        this.element.addEventListener('input', (e) => {
            this.#clearFieldError(e);

            if (e.target.matches('input[name$="[weight]"]')) {
                updateLiftedWeightForInput(e.target, this.liftedWeightTemplateValue);
            }
        });

        initLiftedWeights(this.element, this.liftedWeightTemplateValue);
    }

    disconnect() {
        window.removeEventListener('exercise:selected', this.boundHandler);
        window.removeEventListener('routine:exercises-loaded', this.boundRoutineHandler);
        this.sortable?.destroy();
    }

    // ─── Actions publiques ────────────────────────────────────────

    onExerciseSelected(event) {
        const { html } = event.detail;
        const index = this.exerciseListTarget.children.length;

        this.exerciseListTarget.insertAdjacentHTML('beforeend', html.replaceAll('__EXERCISE_INDEX__', index));
        initLiftedWeights(this.exerciseListTarget.lastElementChild, this.liftedWeightTemplateValue);
    }

    onRoutineExercisesLoaded(event) {
        initLiftedWeights(event.detail.exerciseList, this.liftedWeightTemplateValue);
    }

    addSet(event) {
        const card = event.target.closest('[data-exercise-index]');
        if (this.#blockedByMissingBodyweight(card)) return;

        const tbody = card.querySelector('.js-sets-tbody');

        const newRow = this.#insertSetRow(card, tbody);
        initLiftedWeights(newRow, this.liftedWeightTemplateValue);
    }

    repeatSet(event) {
        const card = event.target.closest('[data-exercise-index]');
        if (this.#blockedByMissingBodyweight(card)) return;

        const sourceRow = event.target.closest('tr');
        const tbody = card.querySelector('.js-sets-tbody');

        const newRow = this.#insertSetRow(card, tbody);
        this.#copySetValues(sourceRow, newRow);
        initLiftedWeights(newRow, this.liftedWeightTemplateValue);
    }

    deleteSet(event) {
        const row = event.target.closest('tr');
        const tbody = row.closest('tbody');

        if (tbody.querySelectorAll('tr').length === 1) {
            this.deleteExercise(event);
            return;
        }

        row.remove();
        numerate(tbody);
    }

    deleteExercise(event) {
        const card = event.target.closest('[data-exercise-index]');
        card.remove();
    }

    async validateAndSubmit(event) {
        event.preventDefault();
        event.stopPropagation();

        const dateInput = document.getElementById('workout_performedAt');

        if (dateInput && !dateInput.value) {
            swalError(this.noDateTitleValue, this.noDateTextValue, '#0f1928', '#f0f4ff', '#f43f5e');
            return;
        }

        if (this.exerciseListTarget.children.length === 0) {
            swalError(this.noExerciseTitleValue, this.noExerciseTextValue, '#0f1928', '#f0f4ff', '#f43f5e');
            return;
        }

        if (!handleErrorField(this.exerciseListTarget)) {
            return;
        }

        this.#noteModalManager.open();
    }

    // ─── Privé ───────────────────────────────────────────────────

    #blockedByMissingBodyweight(card) {
        if (card.dataset.bodyweight !== 'true' || this.userHasBodyweightValue) {
            return false;
        }

        showErrorToast(this.bodyweightRequiredMessageValue);
        return true;
    }

    #insertSetRow(card, tbody) {
        const exerciseIndex = card.dataset.exerciseIndex;
        const setIndex = tbody.querySelectorAll('tr').length;

        const template = card.querySelector('.js-set-template');
        const html = template.innerHTML
            .replaceAll('__EXERCISE_INDEX__', exerciseIndex)
            .replaceAll('__SET_INDEX__', setIndex)
            .replaceAll('__SET_NUMBER__', setIndex + 1);

        tbody.insertAdjacentHTML('beforeend', html);

        return tbody.lastElementChild;
    }

    #copySetValues(sourceRow, newRow) {
        sourceRow.querySelectorAll('input[name]').forEach((input) => {
            const key = input.name.match(/\[(\w+)]$/)?.[1];
            if (!key || key === 'position') {
                return;
            }

            const target = newRow.querySelector(`input[name$="[${key}]"]`);
            if (target) {
                target.value = input.value;
            }
        });
    }

    #updatePositions() {
        this.exerciseListTarget.querySelectorAll('[data-exercise-index]').forEach((card, index) => {
            const positionInput = card.querySelector('.js-position-input');
            if (positionInput) {
                positionInput.value = index;
            }
        });
    }

    #clearFieldError(e) {
        if (e.target.matches('input[required]') && e.target.value) {
            e.target.classList.remove('!border-[rgba(244,63,94,0.6)]');
            e.target.nextElementSibling?.classList.contains('js-error-message') && e.target.nextElementSibling.remove();
        }
    }
}
