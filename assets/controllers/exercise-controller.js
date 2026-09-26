import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { numerate } from './workout/create/number_series.js';
import { swalError } from './swal/error/_error.js';
import { clearFieldError, handleErrorField, revealFirstInvalidField } from './workout/_error_form.js';
import { NoteModalManager } from './workout/create/note_modal_manager.js';
import { showErrorToast } from '../utils/toast.js';
import { initLiftedWeights, updateLiftedWeightForInput } from './workout/lifted_weight.js';
import { insertSetRow, resetPrefilledCard } from './workout/set_rows.js';

export default class extends Controller {
    static targets = ['exerciseList', 'submit'];

    static outlets = ['date'];

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
        missingSetValuesMessage: String,
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
        this.#updateSubmitAvailability();
    }

    disconnect() {
        window.removeEventListener('exercise:selected', this.boundHandler);
        window.removeEventListener('routine:exercises-loaded', this.boundRoutineHandler);
        this.sortable?.destroy();
    }

    // ─── Actions publiques ────────────────────────────────────────

    onExerciseSelected(event) {
        event.detail.htmls.forEach((html) => {
            const index = this.exerciseListTarget.children.length;

            this.exerciseListTarget.insertAdjacentHTML('beforeend', html.replaceAll('__EXERCISE_INDEX__', index));
            initLiftedWeights(this.exerciseListTarget.lastElementChild, this.liftedWeightTemplateValue);
        });
        this.#updateSubmitAvailability();
    }

    onRoutineExercisesLoaded(event) {
        initLiftedWeights(event.detail.exerciseList, this.liftedWeightTemplateValue);
        this.#updateSubmitAvailability();
    }

    addSet(event) {
        const card = event.target.closest('[data-exercise-index]');
        if (this.#blockedByMissingBodyweight(card)) return;

        const tbody = card.querySelector('.js-sets-tbody');

        const newRow = insertSetRow(card, tbody);
        initLiftedWeights(newRow, this.liftedWeightTemplateValue);
        this.#notifyStructureChange();
    }

    clearPrefill(event) {
        const card = event.target.closest('[data-exercise-index]');
        const newRow = resetPrefilledCard(card);
        initLiftedWeights(newRow, this.liftedWeightTemplateValue);
        this.#notifyStructureChange();
    }

    repeatSet(event) {
        const card = event.target.closest('[data-exercise-index]');
        if (this.#blockedByMissingBodyweight(card)) return;

        const sourceRow = event.target.closest('tr');
        const tbody = card.querySelector('.js-sets-tbody');

        const newRow = insertSetRow(card, tbody);
        this.#copySetValues(sourceRow, newRow);
        initLiftedWeights(newRow, this.liftedWeightTemplateValue);
        this.#notifyStructureChange();
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
        this.#notifyStructureChange();
    }

    deleteExercise(event) {
        const card = event.target.closest('[data-exercise-index]');
        card.remove();
        this.#updateSubmitAvailability();
        this.#notifyStructureChange();
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
            revealFirstInvalidField(this.exerciseListTarget);
            showErrorToast(this.missingSetValuesMessageValue);
            return;
        }

        if (this.hasDateOutlet) {
            await this.dateOutlet.checkUnlessDone();
        }

        this.#noteModalManager.open();
    }

    // ─── Privé ───────────────────────────────────────────────────

    // Séries ou cartes ajoutées, retirées ou déplacées : aucun événement de formulaire ne le
    // signale, alors que le brouillon de séance (workout--draft) doit être sauvegardé.
    #notifyStructureChange() {
        window.dispatchEvent(new CustomEvent('workout:changed'));
    }

    #blockedByMissingBodyweight(card) {
        if (card.dataset.bodyweight !== 'true' || this.userHasBodyweightValue) {
            return false;
        }

        showErrorToast(this.bodyweightRequiredMessageValue);
        return true;
    }

    #updateSubmitAvailability() {
        if (!this.hasSubmitTarget) {
            return;
        }

        const cards = [...this.exerciseListTarget.querySelectorAll('[data-exercise-index]')];
        const allBlocked = cards.length > 0 && cards.every((card) => card.dataset.bodyweightBlocked === 'true');

        this.submitTarget.disabled = allBlocked;
        this.submitTarget.title = allBlocked ? this.bodyweightRequiredMessageValue : '';
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
        this.#notifyStructureChange();
    }

    #clearFieldError(e) {
        if (e.target.matches('input[required]') && e.target.value) {
            clearFieldError(e.target);
        }
    }
}
