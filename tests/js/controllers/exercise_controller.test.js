import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application, Controller } from '@hotwired/stimulus';

const fireMock = vi.fn();
vi.mock('sweetalert2', () => ({ default: { fire: fireMock } }));

const ExerciseController = (await import('../../../assets/controllers/exercise-controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom({ dateValue = '', exerciseCards = '', liftedWeightTemplate = 'Poids soulevé : __WEIGHT__' } = {}) {
    document.body.innerHTML = `
        <div data-controller="exercise"
             data-exercise-no-date-title-value="Aucune date sélectionnée"
             data-exercise-no-date-text-value="Choisis une date avant de valider ta séance"
             data-exercise-no-exercise-title-value="Aucun exercice"
             data-exercise-no-exercise-text-value="Ajoute au moins un exercice avant de valider ta séance"
             data-exercise-submit-failed-title-value="Échec"
             data-exercise-submit-failed-text-value="Une erreur est survenue"
             data-exercise-upload-photo-url-value="/upload/__ID__"
             data-exercise-missing-set-values-message-value="Complète les séries en rouge"
             data-exercise-lifted-weight-template-value="${liftedWeightTemplate}">

            <input id="workout_performedAt" type="text" value="${dateValue}">

            <div data-exercise-target="exerciseList" id="exercises-list">${exerciseCards}</div>

            <button data-action="click->exercise#validateAndSubmit"></button>
        </div>

        <div id="note-modal" class="hidden">
            <button id="note-modal-back"></button>
            <button id="note-modal-submit"></button>
            <textarea id="workout-note-input"></textarea>
        </div>
    `;
}

describe('exercise controller', () => {
    let application;

    beforeEach(() => {
        fireMock.mockClear();
        application = Application.start();
        application.register('exercise', ExerciseController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('shows a "no date" error and does not open the note modal when the date is empty', async () => {
        buildDom({ dateValue: '', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        document.querySelector('button[data-action]').click();
        await nextTick();

        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(fireMock.mock.calls[0][0]).toMatchObject({
            title: 'Aucune date sélectionnée',
            text: 'Choisis une date avant de valider ta séance',
        });
        const modal = document.getElementById('note-modal');
        expect(modal.classList.contains('flex')).toBe(false);
    });

    it('shows the "no exercise" error (not the date error) once a date is set but no exercise added', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '' });
        await nextTick();

        document.querySelector('button[data-action]').click();
        await nextTick();

        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(fireMock.mock.calls[0][0]).toMatchObject({ title: 'Aucun exercice' });
        const modal = document.getElementById('note-modal');
        expect(modal.classList.contains('flex')).toBe(false);
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

    it('opens the note modal when a date and at least one exercise are present', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        document.querySelector('button[data-action]').click();
        await nextTick();

        const modal = document.getElementById('note-modal');
        expect(modal.classList.contains('flex')).toBe(true);
    });

    it('reveals the first empty set field with a toast instead of opening the note modal', async () => {
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

        document.querySelector('button[data-action]').click();
        await nextTick();

        expect(emptyReps.scrollIntoView).toHaveBeenCalledTimes(1);
        expect(document.activeElement).toBe(emptyReps);
        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(fireMock.mock.calls[0][0]).toMatchObject({ toast: true, title: 'Complète les séries en rouge' });
        expect(document.getElementById('note-modal').classList.contains('flex')).toBe(false);
    });

    it('clears a prefilled card back to a single empty set', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: `
                <div data-exercise-index="0">
                    <div class="js-prefilled-from"><button data-action="click->exercise#clearPrefill">Effacer</button></div>
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

    it('checks the workout date before opening the note modal', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        const events = [];
        const checkUnlessDone = vi.fn(async () => events.push('date checked'));
        application.register('date', class extends Controller {
            checkUnlessDone = checkUnlessDone;
        });
        document.getElementById('workout_performedAt').setAttribute('data-controller', 'date');
        document.querySelector('[data-controller="exercise"]').setAttribute('data-exercise-date-outlet', '#workout_performedAt');
        await nextTick();
        new MutationObserver(() => {
            if (document.getElementById('note-modal').classList.contains('flex')) {
                events.push('note modal opened');
            }
        }).observe(document.getElementById('note-modal'), { attributes: true });

        document.querySelector('button[data-action]').click();
        await nextTick();
        await nextTick();

        expect(checkUnlessDone).toHaveBeenCalledTimes(1);
        expect(events).toEqual(['date checked', 'note modal opened']);
    });

    it('computes the lifted weight of a routine\'s bodyweight sets once loaded', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '' });
        await nextTick();

        const exerciseList = document.getElementById('exercises-list');
        exerciseList.insertAdjacentHTML('beforeend', `
            <div data-exercise-index="0">
                <table><tbody><tr>
                    <td>
                        <input type="number" value="10">
                        <span class="js-lifted-weight" data-bodyweight-share="70"></span>
                    </td>
                </tr></tbody></table>
            </div>
        `);

        window.dispatchEvent(new CustomEvent('routine:exercises-loaded', { detail: { exerciseList } }));
        await nextTick();

        expect(document.querySelector('.js-lifted-weight').textContent).toBe('Poids soulevé : 80.0');
    });
});
