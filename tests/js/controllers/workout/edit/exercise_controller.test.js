import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application, Controller } from '@hotwired/stimulus';

const fireMock = vi.fn();
vi.mock('sweetalert2', () => ({ default: { fire: fireMock } }));

const WorkoutEditExerciseController = (await import('../../../../../assets/controllers/workout/edit/exercise_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom({ dateValue = '', exerciseCards = '' } = {}) {
    document.body.innerHTML = `
        <div data-controller="workout--edit--exercise"
             data-workout--edit--exercise-no-date-title-value="Aucune date sélectionnée"
             data-workout--edit--exercise-no-date-text-value="Choisis une date avant de valider ta séance"
             data-workout--edit--exercise-no-exercise-title-value="Aucun exercice"
             data-workout--edit--exercise-no-exercise-text-value="Ajoute au moins un exercice avant de valider ta séance"
             data-workout--edit--exercise-missing-set-values-message-value="Complète les séries en rouge">
            <div data-workout--edit--exercise-target="exerciseList">${exerciseCards}</div>
        </div>

        <input id="workout_performedAt" type="text" value="${dateValue}">

        <form id="workout-edit-form"></form>
        <button id="workout-edit-submit-btn" type="button"></button>
    `;
}

describe('workout--edit--exercise controller', () => {
    let application;

    beforeEach(() => {
        fireMock.mockClear();
        application = Application.start();
        application.register('workout--edit--exercise', WorkoutEditExerciseController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('shows a "no date" error and does not submit the form when the date is empty', async () => {
        buildDom({ dateValue: '', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        const form = document.getElementById('workout-edit-form');
        const requestSubmit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});

        document.getElementById('workout-edit-submit-btn').click();

        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(fireMock.mock.calls[0][0]).toMatchObject({
            title: 'Aucune date sélectionnée',
            text: 'Choisis une date avant de valider ta séance',
        });
        expect(requestSubmit).not.toHaveBeenCalled();
    });

    it('appends every selected exercise card, in order, with its real index', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        window.dispatchEvent(new CustomEvent('exercise:selected', { detail: { htmls: [
            '<div data-exercise-index="__EXERCISE_INDEX__" data-name="squat"></div>',
            '<div data-exercise-index="__EXERCISE_INDEX__" data-name="bench"></div>',
        ] } }));
        await nextTick();

        const added = [...document.querySelectorAll('[data-name]')];
        expect(added.map((card) => card.dataset.name)).toEqual(['squat', 'bench']);
        expect(added.map((card) => card.dataset.exerciseIndex)).toEqual(['1', '2']);
    });

    it('submits the form when a date and at least one exercise are present', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        const form = document.getElementById('workout-edit-form');
        const requestSubmit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});

        document.getElementById('workout-edit-submit-btn').click();
        await nextTick();

        expect(fireMock).not.toHaveBeenCalled();
        expect(requestSubmit).toHaveBeenCalledTimes(1);
    });

    it('reveals the first empty set field with a toast instead of submitting', async () => {
        buildDom({
            dateValue: '2026-08-02',
            exerciseCards: `
                <div data-exercise-index="0">
                    <input type="number" name="reps" required data-error-message="Les répétitions sont requises">
                </div>
            `,
        });
        await nextTick();
        const emptyReps = document.querySelector('input[name="reps"]');
        emptyReps.scrollIntoView = vi.fn();
        const form = document.getElementById('workout-edit-form');
        const requestSubmit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});

        document.getElementById('workout-edit-submit-btn').click();
        await nextTick();

        expect(emptyReps.scrollIntoView).toHaveBeenCalledTimes(1);
        expect(document.activeElement).toBe(emptyReps);
        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(fireMock.mock.calls[0][0]).toMatchObject({ toast: true, title: 'Complète les séries en rouge' });
        expect(requestSubmit).not.toHaveBeenCalled();
    });

    it('clears a prefilled card back to a single empty set', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: `
                <div data-exercise-index="0">
                    <div class="js-prefilled-from"><button data-action="click->workout--edit--exercise#clearPrefill">Effacer</button></div>
                    <table><tbody class="js-sets-tbody">
                        <tr><td><input name="w[0][exerciseSets][0][weight]" value="80"></td></tr>
                        <tr><td><input name="w[0][exerciseSets][1][weight]" value="85"></td></tr>
                    </tbody></table>
                    <template class="js-set-template"><tr><td><input name="w[__EXERCISE_INDEX__][exerciseSets][__SET_INDEX__][weight]"></td></tr></template>
                </div>
            ` });
        await nextTick();

        document.querySelector('.js-prefilled-from button').click();

        const inputs = document.querySelectorAll('.js-sets-tbody input');
        expect(inputs).toHaveLength(1);
        expect(inputs[0].value).toBe('');
        expect(document.querySelector('.js-prefilled-from')).toBeNull();
    });

    it('commits pending photo changes before submitting when a photo-upload controller is present', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        document.body.insertAdjacentHTML('beforeend', '<div data-controller="workout--photo-upload"></div>');
        await nextTick();

        const form = document.getElementById('workout-edit-form');
        vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});

        const commitPendingChanges = vi.fn().mockResolvedValue(null);
        application.register('workout--photo-upload', class extends Controller {
            commitPendingChanges = commitPendingChanges;
        });
        await nextTick();

        document.getElementById('workout-edit-submit-btn').click();
        await nextTick();

        expect(commitPendingChanges).toHaveBeenCalledTimes(1);
    });
});
