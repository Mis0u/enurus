import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

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

    it('opens the note modal when a date and at least one exercise are present', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        document.querySelector('button[data-action]').click();
        await nextTick();

        const modal = document.getElementById('note-modal');
        expect(modal.classList.contains('flex')).toBe(true);
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
