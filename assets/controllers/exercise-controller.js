import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';
import { numerate } from './workout/create/number_series.js';
import { swalError } from './swal/error/_error.js';
import { handleErrorField } from './workout/_error_form.js';

export default class extends Controller {
    static targets = ['exerciseList', 'form'];

    static values = {
        blockUrl: String,
        noExerciseTitle: String,
        noExerciseText: String,
        noteSubmitWithout: String,
        noteSubmitWith: String,
    };

    connect() {
        this.boundHandler = this.onExerciseSelected.bind(this);
        window.addEventListener('exercise:selected', this.boundHandler);

        this.sortable = new Sortable(this.exerciseListTarget, {
            animation: 250,
            handle: '.drag-handle',
            ghostClass: 'opacity-30',
            chosenClass: 'opacity-50',
            easing: 'cubic-bezier(0.4, 0, 0.2, 1)',
            onEnd: () => this.updatePositions(),
        });

        this.element.addEventListener('input', (e) => {
            if (e.target.matches('input[required]') && e.target.value) {
                e.target.classList.remove('!border-[rgba(244,63,94,0.6)]');
                e.target.nextElementSibling?.classList.contains('js-error-message') && e.target.nextElementSibling.remove();
            }
        });
    }

    disconnect() {
        window.removeEventListener('exercise:selected', this.boundHandler);
        this.sortable?.destroy();
    }

    async onExerciseSelected(event) {
        const { id } = event.detail;
        const index = this.exerciseListTarget.children.length;

        const response = await fetch(
            `${this.blockUrlValue}?exerciseId=${id}&index=${index}`
        );

        if (!response.ok) {
            return;
        }

        const html = await response.text();
        this.exerciseListTarget.insertAdjacentHTML('beforeend', html);
    }

    addSet(event) {
        const card = event.target.closest('[data-exercise-index]');
        const tbody = card.querySelector('.js-sets-tbody');
        const exerciseIndex = card.dataset.exerciseIndex;
        const setIndex = tbody.querySelectorAll('tr').length;

        const template = card.querySelector('.js-set-template');
        const html = template.innerHTML
            .replaceAll('__EXERCISE_INDEX__', exerciseIndex)
            .replaceAll('__SET_INDEX__', setIndex)
            .replaceAll('__SET_NUMBER__', setIndex + 1);

        tbody.insertAdjacentHTML('beforeend', html);
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

        if (this.exerciseListTarget.children.length === 0) {
            swalError(this.noExerciseTitleValue, this.noExerciseTextValue, '#0f1928','#f0f4ff','#f43f5e');
            return;
        }

        let valid = handleErrorField(this.exerciseListTarget);

        if (!valid) {
            return;
        }

        this.openNoteModal();
    }

    updatePositions() {
        this.exerciseListTarget.querySelectorAll('[data-exercise-index]').forEach((card, index) => {
            const positionInput = card.querySelector('.js-position-input');
            if (positionInput) {
                positionInput.value = index;
            }
        });
    }

    openNoteModal() {
        const modal = document.getElementById('note-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        const textarea = document.getElementById('workout-note-input');
        const submitBtn = document.getElementById('note-modal-submit');
        const backBtn = document.getElementById('note-modal-back');

        // Textes traduits via data-values
        const submitWithout = this.noteSubmitWithoutValue;
        const submitWith = this.noteSubmitWithValue;

        // Reset
        textarea.value = '';
        submitBtn.textContent = submitWithout;

        // Bouton change de texte selon la note
        textarea.addEventListener('input', () => {
            submitBtn.textContent = textarea.value.trim()
                ? submitWith
                : submitWithout;
        });

        // Retour à la séance
        backBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });

        // Soumission
        submitBtn.addEventListener('click', () => {
            const noteInput = document.getElementById('workout_note');
            if (noteInput) {
                noteInput.value = textarea.value.trim();
            }
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            document.querySelector('form').requestSubmit();
        });
    }
}
