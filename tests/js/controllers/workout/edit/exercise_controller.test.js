import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

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
             data-workout--edit--exercise-no-exercise-text-value="Ajoute au moins un exercice avant de valider ta séance">
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

    it('submits the form when a date and at least one exercise are present', async () => {
        buildDom({ dateValue: '2026-08-02', exerciseCards: '<div data-exercise-index="0"></div>' });
        await nextTick();

        const form = document.getElementById('workout-edit-form');
        const requestSubmit = vi.spyOn(form, 'requestSubmit').mockImplementation(() => {});

        document.getElementById('workout-edit-submit-btn').click();

        expect(fireMock).not.toHaveBeenCalled();
        expect(requestSubmit).toHaveBeenCalledTimes(1);
    });
});
