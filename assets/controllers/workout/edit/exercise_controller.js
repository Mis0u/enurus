import { Controller } from '@hotwired/stimulus';
import Swal          from 'sweetalert2';
import Sortable      from 'sortablejs';
import { handleErrorField } from '../_error_form.js';

export default class extends Controller {

    static targets = ['exerciseList'];

    static values = {
        noDateTitle: String,
        noDateText: String,
        noExerciseTitle: String,
        noExerciseText:  String,
    };

    connect() {
        this.boundHandler = this.onExerciseSelected.bind(this);
        window.addEventListener('exercise:selected', this.boundHandler);

        this.sortable = new Sortable(this.exerciseListTarget, {
            handle:      '.drag-handle',
            animation:   250,
            ghostClass:  'sortable-ghost',
            dragClass:   'sortable-drag',
            easing:      'cubic-bezier(0.4, 0, 0.2, 1)',
            onEnd:       () => this.updatePositions(),
        });

        this.element.addEventListener('input', (e) => {
            if (e.target.matches('input[required]') && e.target.value) {
                e.target.classList.remove('!border-[rgba(244,63,94,0.6)]');
                const next = e.target.nextElementSibling;
                if (next?.classList.contains('js-error-message')) {
                    next.remove();
                }
            }
        });

        this.boundSubmitHandler = this.onSubmitClick.bind(this);
        this._attachSubmitBtn();

        this.boundTurboLoad = () => this._attachSubmitBtn();
        document.addEventListener('turbo:load', this.boundTurboLoad);
    }

    disconnect() {
        window.removeEventListener('exercise:selected', this.boundHandler);
        document.removeEventListener('turbo:load', this.boundTurboLoad);
        this.sortable?.destroy();

        const btn = document.getElementById('workout-edit-submit-btn');
        if (btn) {
            btn.removeEventListener('click', this.boundSubmitHandler);
        }
    }

    _attachSubmitBtn() {
        const btn = document.getElementById('workout-edit-submit-btn');
        if (btn) {
            btn.removeEventListener('click', this.boundSubmitHandler);
            btn.addEventListener('click', this.boundSubmitHandler);
        } else {
            console.warn('submit btn NOT found');
        }
    }

    async onSubmitClick(event) {
        event.preventDefault();

        const dateInput = document.getElementById('workout_performedAt');

        if (dateInput && !dateInput.value) {
            Swal.fire({
                icon:               'error',
                title:              this.noDateTitleValue,
                text:               this.noDateTextValue,
                background:         '#0f1928',
                color:              '#f0f4ff',
                confirmButtonColor: '#f43f5e',
            });
            return;
        }

        if (this.exerciseListTarget.children.length === 0) {
            Swal.fire({
                icon:               'error',
                title:              this.noExerciseTitleValue,
                text:               this.noExerciseTextValue,
                background:         '#0f1928',
                color:              '#f0f4ff',
                confirmButtonColor: '#f43f5e',
            });
            return;
        }

        const valid = handleErrorField(this.exerciseListTarget);
        if (!valid) return;

        // Photo (upload d'un nouveau fichier ou suppression) reste en attente jusqu'ici — jamais
        // appliquée tant que le formulaire n'est pas réellement soumis (cf. bug "Annuler" gardant
        // quand même la modification de photo).
        await this._commitPendingPhotoChanges();

        document.getElementById('workout-edit-form').requestSubmit();
    }

    async _commitPendingPhotoChanges() {
        const photoUploadEl = document.querySelector('[data-controller~="workout--photo-upload"]');
        if (!photoUploadEl) return;

        const photoController = this.application.getControllerForElementAndIdentifier(photoUploadEl, 'workout--photo-upload');
        await photoController?.commitPendingChanges();
    }

    openModal() {
        window.dispatchEvent(new CustomEvent('exercise-selector:open'));
    }

    onExerciseSelected(event) {
        const { html } = event.detail;
        const index    = this.exerciseListTarget.children.length;

        this.exerciseListTarget.insertAdjacentHTML('beforeend', html.replaceAll('__EXERCISE_INDEX__', index));
    }

    addSet(event) {
        const card          = event.target.closest('[data-exercise-index]');
        const tbody         = card.querySelector('.js-sets-tbody');
        const exerciseIndex = card.dataset.exerciseIndex;
        const setIndex      = tbody.querySelectorAll('tr').length;
        const template      = card.querySelector('.js-set-template');

        const html = template.innerHTML
            .replaceAll('__EXERCISE_INDEX__', exerciseIndex)
            .replaceAll('__SET_INDEX__',      setIndex)
            .replaceAll('__SET_NUMBER__',     setIndex + 1);

        tbody.insertAdjacentHTML('beforeend', html);
    }

    deleteSet(event) {
        const row   = event.target.closest('tr');
        const tbody = row.closest('tbody');

        if (tbody.querySelectorAll('tr').length === 1) {
            tbody.closest('[data-exercise-index]').remove();
            this.updatePositions();
            return;
        }

        row.remove();
        this._renumberSets(tbody);
    }

    deleteExercise(event) {
        const card = event.target.closest('[data-exercise-index]');

        card.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
        card.style.opacity    = '0';
        card.style.transform  = 'translateY(-8px)';

        setTimeout(() => {
            card.remove();
            this.updatePositions();
        }, 220);
    }

    updatePositions() {
        this.exerciseListTarget
            .querySelectorAll('[data-exercise-index]')
            .forEach((card, index) => {
                card.dataset.exerciseIndex = index;

                const positionInput = card.querySelector('.js-position-input');
                if (positionInput) positionInput.value = index;

                card.querySelectorAll('input[name]').forEach(input => {
                    input.name = input.name.replace(
                        /\[workoutExercises\]\[\d+\]/,
                        `[workoutExercises][${index}]`,
                    );
                });

                const badge = card.querySelector('[data-exercise-number]');
                if (badge) badge.textContent = index + 1;
            });
    }

    _renumberSets(tbody) {
        tbody.querySelectorAll('tr').forEach((tr, i) => {
            tr.dataset.setIndex = i;
            tr.querySelector('td:first-child').textContent = i + 1;
            tr.querySelectorAll('input').forEach(input => {
                input.name = input.name.replace(
                    /\[exerciseSets\]\[\d+\]/,
                    `[exerciseSets][${i}]`,
                );
                if (input.type === 'hidden') input.value = i;
            });
        });
    }
}
