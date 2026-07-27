import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const sortableInstances = [];

vi.mock('sortablejs', () => ({
    default: class Sortable {
        constructor(el, options) {
            this.el = el;
            this.options = options;
            sortableInstances.push(this);
        }

        destroy() {}
    },
}));

const RoutineCreateController = (await import('../../../../assets/controllers/routine/create_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function exerciseItem(id, name, { primaryGroupIds = '', secondaryGroupIds = '', primaryTag = '', secondaryTag = '' } = {}) {
    return `
        <div data-routine--create-target="exerciseItem"
             data-exercise-id="${id}"
             data-exercise-name="${name}"
             data-primary-muscle-group-ids="${primaryGroupIds}"
             data-secondary-muscle-group-ids="${secondaryGroupIds}">
            ${primaryTag ? `<span data-muscle-type="primary">${primaryTag}</span>` : ''}
            ${secondaryTag ? `<span data-muscle-type="secondary">${secondaryTag}</span>` : ''}
            <button data-exercise-id="${id}" data-action="click->routine--create#toggleExercise"></button>
        </div>
    `;
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="routine--create"
             data-routine--create-error-name-value="Nom requis"
             data-routine--create-error-exercise-value="Ajoute au moins un exercice"
             data-routine--create-error-name-exists-value="Ce nom existe déjà"
             data-routine--create-check-name-url-value="/routine/verifie-nom"
             data-routine--create-label-singular-value="exercice"
             data-routine--create-label-plural-value="exercices">

            <div><input data-routine--create-target="nameInput"
                   data-action="input->routine--create#updateHeader"></div>
            <input type="hidden" data-routine--create-target="exercisesInput">
            <input data-routine--create-target="searchInput"
                   data-action="input->routine--create#filterExercises">

            <button data-routine--create-target="muscleFilterChip" data-muscle-id="chest"
                    data-action="click->routine--create#cycleMuscleFilter">Chest</button>
            <span data-routine--create-target="muscleFilterCount" hidden></span>

            <div>
                ${exerciseItem('ex-1', 'Squat', { primaryGroupIds: 'legs', primaryTag: 'Legs' })}
                ${exerciseItem('ex-2', 'Bench press', { primaryGroupIds: 'chest', secondaryGroupIds: 'triceps', primaryTag: 'Chest', secondaryTag: 'Triceps' })}
            </div>

            <div data-routine--create-target="searchEmptyState" hidden></div>

            <div data-routine--create-target="selectedList"></div>
            <div><div data-routine--create-target="emptyState"></div></div>

            <span data-routine--create-target="summaryName"></span>
            <span data-routine--create-target="summaryCount" hidden></span>

            <button data-routine--create-target="submitBtn" data-action="click->routine--create#submitForm"></button>

            <button data-routine--create-target="musclePreviewToggle" aria-expanded="false"
                    data-action="click->routine--create#toggleMusclePreview"><svg></svg></button>
            <div data-routine--create-target="musclePreviewBody"></div>
            <div data-routine--create-target="muscleBody"></div>

            <button data-action="click->routine--create#toggleAccordion" aria-expanded="false"></button>
            <div data-routine--create-target="accordionBody"></div>
        </div>
    `;
}

describe('routine--create controller', () => {
    let application;

    beforeEach(async () => {
        sortableInstances.length = 0;
        buildDom();
        application = Application.start();
        application.register('routine--create', RoutineCreateController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    function toggleBtn(id) {
        return document.querySelector(`button[data-exercise-id="${id}"]`);
    }

    it('adds an exercise to the selected list and updates the summary', () => {
        toggleBtn('ex-1').click();

        expect(document.querySelector('[data-routine--create-target="selectedList"] [data-exercise-id="ex-1"]')).not.toBeNull();
        expect(document.querySelector('[data-routine--create-target="emptyState"]').hidden).toBe(true);
        expect(document.querySelector('[data-routine--create-target="summaryCount"]').textContent).toBe('1 exercice');
    });

    it('removes an exercise when toggled again and restores the empty state', () => {
        toggleBtn('ex-1').click();
        toggleBtn('ex-1').click();

        expect(document.querySelector('[data-routine--create-target="selectedList"] [data-exercise-id="ex-1"]')).toBeNull();
        expect(document.querySelector('[data-routine--create-target="emptyState"]').hidden).toBe(false);
    });

    it('removeExercise (right column cross button) removes the exercise and re-idles the left item', async () => {
        toggleBtn('ex-1').click();
        // L'élément est injecté dynamiquement dans le DOM : Stimulus doit d'abord détecter la
        // mutation (MutationObserver) avant que son data-action ne soit effectivement lié.
        await nextTick();
        document.querySelector('[data-routine--create-target="selectedList"] button').click();

        expect(document.querySelector('[data-routine--create-target="selectedList"] [data-exercise-id="ex-1"]')).toBeNull();
        expect(document.querySelector('[data-routine--create-target="exercisesInput"]').value).toBe('[]');
    });

    it('syncs the hidden JSON input with ids and 1-based positions', () => {
        toggleBtn('ex-1').click();
        toggleBtn('ex-2').click();

        const data = JSON.parse(document.querySelector('[data-routine--create-target="exercisesInput"]').value);
        expect(data).toEqual([{ id: 'ex-1', position: 1 }, { id: 'ex-2', position: 2 }]);
    });

    it('filters exercises by search text and shows the empty state when nothing matches', () => {
        const searchInput = document.querySelector('[data-routine--create-target="searchInput"]');
        searchInput.value = 'squat';
        searchInput.dispatchEvent(new Event('input'));

        expect(document.querySelector('[data-exercise-id="ex-1"]').hidden).toBe(false);
        expect(document.querySelector('[data-exercise-id="ex-2"]').hidden).toBe(true);
        expect(document.querySelector('[data-routine--create-target="searchEmptyState"]').hidden).toBe(true);

        searchInput.value = 'nonexistent';
        searchInput.dispatchEvent(new Event('input'));

        expect(document.querySelector('[data-routine--create-target="searchEmptyState"]').hidden).toBe(false);
    });

    it('cycling a muscle filter chip filters exercises assigned to that muscle as primary', () => {
        const chip = document.querySelector('[data-routine--create-target="muscleFilterChip"]');
        chip.click(); // none -> primary

        expect(document.querySelector('[data-exercise-id="ex-1"]').hidden).toBe(true);
        expect(document.querySelector('[data-exercise-id="ex-2"]').hidden).toBe(false);
        expect(document.querySelector('[data-routine--create-target="muscleFilterCount"]').hidden).toBe(false);
    });

    it('reorders exercises and renumbers positions when Sortable reports onEnd', () => {
        toggleBtn('ex-1').click();
        toggleBtn('ex-2').click();

        const selectedList = document.querySelector('[data-routine--create-target="selectedList"]');
        // Simule un drag : ex-2 passe devant ex-1 dans le DOM.
        selectedList.insertBefore(
            selectedList.querySelector('[data-exercise-id="ex-2"]'),
            selectedList.querySelector('[data-exercise-id="ex-1"]'),
        );

        sortableInstances[0].options.onEnd();

        const data = JSON.parse(document.querySelector('[data-routine--create-target="exercisesInput"]').value);
        expect(data).toEqual([{ id: 'ex-2', position: 1 }, { id: 'ex-1', position: 2 }]);
    });

    it('submitForm blocks submission and shows both errors when name is empty and no exercise is selected', () => {
        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="routine--create"]'),
            'routine--create',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(true);
        expect(document.querySelectorAll('.field-error')).toHaveLength(2);
    });

    it('submitForm allows submission once a name is filled and an exercise is selected', () => {
        document.querySelector('[data-routine--create-target="nameInput"]').value = 'Push day';
        toggleBtn('ex-1').click();

        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="routine--create"]'),
            'routine--create',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('setNameAvailable(false) disables the submit button even with a valid selection', () => {
        document.querySelector('[data-routine--create-target="nameInput"]').value = 'Push day';
        toggleBtn('ex-1').click();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="routine--create"]'),
            'routine--create',
        );
        controller.setNameAvailable(false);

        expect(document.querySelector('[data-routine--create-target="submitBtn"]').disabled).toBe(true);
    });
});
