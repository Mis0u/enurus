import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';

const ExerciseSelectorController = (await import('../../../assets/controllers/exercise_selector_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

// Même exercice « squat » deux fois : dans « Tes habituels » et dans la liste complète.
function buildDom(selectedIds = []) {
    document.body.innerHTML = `
        <div data-controller="exercise-selector"
             data-exercise-selector-selected-ids-value='${JSON.stringify(selectedIds)}'
             data-exercise-selector-add-label-value="Ajouter (__COUNT__)">
            <input type="checkbox" value="squat" data-action="change->exercise-selector#toggle" ${selectedIds.includes('squat') ? 'checked' : ''}>
            <input type="checkbox" value="squat" data-action="change->exercise-selector#toggle" ${selectedIds.includes('squat') ? 'checked' : ''}>
            <input type="checkbox" value="bench" data-action="change->exercise-selector#toggle" ${selectedIds.includes('bench') ? 'checked' : ''}>
            <button data-exercise-selector-target="addButton"></button>
        </div>
    `;
}

function toggle(checkbox) {
    checkbox.checked = !checkbox.checked;
    checkbox.dispatchEvent(new Event('change', { bubbles: true }));
}

describe('exercise-selector controller', () => {
    let application;

    beforeEach(() => {
        application = Application.start();
        application.register('exercise-selector', ExerciseSelectorController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('disables the add button while nothing is selected', async () => {
        buildDom();
        await nextTick();

        const button = document.querySelector('button');
        expect(button.disabled).toBe(true);
        expect(button.textContent).toBe('Ajouter (0)');
    });

    it('counts the exercises selected before the last render, including hidden ones', async () => {
        // « deadlift » est coché mais masqué par la recherche : aucune case dans le DOM.
        buildDom(['bench', 'deadlift']);
        await nextTick();

        const button = document.querySelector('button');
        expect(button.disabled).toBe(false);
        expect(button.textContent).toBe('Ajouter (2)');
    });

    it('updates the count on each check and uncheck', async () => {
        buildDom();
        await nextTick();
        const bench = document.querySelector('input[value="bench"]');

        toggle(bench);
        expect(document.querySelector('button').textContent).toBe('Ajouter (1)');

        toggle(bench);
        expect(document.querySelector('button').textContent).toBe('Ajouter (0)');
    });

    it('keeps both boxes of an exercise listed twice in sync and counts it once', async () => {
        buildDom();
        await nextTick();
        const [habitual, inFullList] = document.querySelectorAll('input[value="squat"]');

        toggle(habitual);

        expect(inFullList.checked).toBe(true);
        expect(document.querySelector('button').textContent).toBe('Ajouter (1)');
    });
});
