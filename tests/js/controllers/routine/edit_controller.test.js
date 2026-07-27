import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('sortablejs', () => ({
    default: class Sortable {
        constructor(el, options) {
            this.el = el;
            this.options = options;
        }

        destroy() {}
    },
}));

const RoutineEditController = (await import('../../../../assets/controllers/routine/edit_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function exerciseItem(id, name, { primaryGroupIds = '', primaryTag = '' } = {}) {
    return `
        <div data-routine--edit-target="exerciseItem"
             data-exercise-id="${id}"
             data-exercise-name="${name}"
             data-primary-muscle-group-ids="${primaryGroupIds}">
            ${primaryTag ? `<span data-muscle-type="primary">${primaryTag}</span>` : ''}
            <button data-exercise-id="${id}" data-action="click->routine--edit#toggleExercise"></button>
        </div>
    `;
}

function preSelectedItem(id) {
    return `
        <div data-exercise-id="${id}">
            <span data-routine--edit-target="itemPosition"></span>
            <button data-exercise-id="${id}" data-action="click->routine--edit#removeExercise"></button>
        </div>
    `;
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="routine--edit"
             data-routine--edit-error-name-value="Nom requis"
             data-routine--edit-error-exercise-value="Ajoute au moins un exercice"
             data-routine--edit-error-name-exists-value="Ce nom existe déjà"
             data-routine--edit-check-name-url-value="/routine/verifie-nom"
             data-routine--edit-exclude-id-value="routine-42"
             data-routine--edit-label-singular-value="exercice"
             data-routine--edit-label-plural-value="exercices">

            <div><input data-routine--edit-target="nameInput" value="Push day"></div>
            <input type="hidden" data-routine--edit-target="exercisesInput">
            <input data-routine--edit-target="searchInput"
                   data-action="input->routine--edit#filterExercises">

            <button data-routine--edit-target="muscleFilterChip" data-muscle-id="chest"
                    data-action="click->routine--edit#cycleMuscleFilter">Chest</button>
            <span data-routine--edit-target="muscleFilterCount" hidden></span>

            <div>
                ${exerciseItem('ex-1', 'Squat', { primaryGroupIds: 'legs', primaryTag: 'Legs' })}
                ${exerciseItem('ex-2', 'Bench press', { primaryGroupIds: 'chest', primaryTag: 'Chest' })}
            </div>

            <div data-routine--edit-target="searchEmptyState" hidden></div>

            <div data-routine--edit-target="selectedList">
                ${preSelectedItem('ex-1')}
            </div>
            <div><div data-routine--edit-target="emptyState"></div></div>

            <span data-routine--edit-target="summaryName"></span>
            <span data-routine--edit-target="summaryCount" hidden></span>

            <button data-routine--edit-target="submitBtn" data-action="click->routine--edit#submitForm"></button>

            <button data-action="click->routine--edit#toggleAccordion" aria-expanded="false"></button>
            <div data-routine--edit-target="accordionBody"></div>
        </div>
    `;
}

describe('routine--edit controller', () => {
    let application;

    beforeEach(async () => {
        buildDom();
        application = Application.start();
        application.register('routine--edit', RoutineEditController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    function toggleBtn(id) {
        return document.querySelector(`button[data-exercise-id="${id}"][data-action*="toggleExercise"]`);
    }

    it('initializes selected ids from the pre-rendered selected list and marks the matching left item as added', () => {
        expect(document.querySelector('[data-routine--edit-target="exercisesInput"]').value).toBe('[{"id":"ex-1","position":1}]');
        expect(document.querySelector('[data-routine--edit-target="emptyState"]').hidden).toBe(true);
        expect(document.querySelector('[data-routine--edit-target="summaryCount"]').textContent).toBe('1 exercice');
    });

    it('adding a second exercise appends it after the pre-existing one with position 2', () => {
        toggleBtn('ex-2').click();

        const data = JSON.parse(document.querySelector('[data-routine--edit-target="exercisesInput"]').value);
        expect(data).toEqual([{ id: 'ex-1', position: 1 }, { id: 'ex-2', position: 2 }]);
    });

    it('removing the only pre-selected exercise restores the empty state', async () => {
        document.querySelector('[data-routine--edit-target="selectedList"] button').click();
        await nextTick();

        expect(document.querySelector('[data-routine--edit-target="emptyState"]').hidden).toBe(false);
        expect(document.querySelector('[data-routine--edit-target="exercisesInput"]').value).toBe('[]');
    });

    it('includes excludeId in the name-check request (edit-only behavior)', () => {
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="routine--edit"]'),
            'routine--edit',
        );

        expect(controller.hasExcludeIdValue).toBe(true);
        expect(controller.excludeIdValue).toBe('routine-42');
    });

    it('submitForm allows submission since a name and a pre-selected exercise already exist', () => {
        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="routine--edit"]'),
            'routine--edit',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(false);
    });
});
