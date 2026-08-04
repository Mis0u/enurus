import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn();
vi.mock('sweetalert2', () => ({ default: { fire: fireMock } }));

const RoutineLoaderController = (await import('../../../../assets/controllers/workout/routine-loader_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom({ exercisesHtml = '' } = {}) {
    document.body.innerHTML = `
        <select data-controller="workout--routine-loader"
                data-workout--routine-loader-target="select"
                data-workout--routine-loader-block-url-value="/routine-exercises"
                data-workout--routine-loader-warning-title-value="Changer de routine ?"
                data-workout--routine-loader-warning-text-value="Cela effacera tes exercices."
                data-workout--routine-loader-confirm-button-value="Continuer"
                data-workout--routine-loader-cancel-button-value="Annuler"
                data-action="change->workout--routine-loader#onChange">
            <option value="">Choisis ta routine</option>
            <option value="routine-1">Push day</option>
            <option value="routine-2">Full body</option>
        </select>
        <div id="exercises-list">${exercisesHtml}</div>
    `;
}

function selectRoutine(value) {
    const select = document.querySelector('select');
    select.value = value;
    select.dispatchEvent(new Event('change'));
}

describe('workout--routine-loader controller', () => {
    let application;

    beforeEach(() => {
        fireMock.mockReset();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            text: () => Promise.resolve('<div class="exercise-card"></div>'),
        }));

        application = Application.start();
        application.register('workout--routine-loader', RoutineLoaderController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('loads a routine straight away when the exercise list is empty', async () => {
        buildDom();
        await nextTick();

        selectRoutine('routine-1');
        await nextTick();
        await nextTick();

        expect(fireMock).not.toHaveBeenCalled();
        expect(document.getElementById('exercises-list').children.length).toBe(1);
    });

    it('clears the exercise list without confirmation when going back to the placeholder while empty', async () => {
        buildDom();
        await nextTick();

        selectRoutine('');
        await nextTick();

        expect(fireMock).not.toHaveBeenCalled();
        expect(document.getElementById('exercises-list').innerHTML).toBe('');
    });

    it('asks for confirmation and clears the list when selecting the placeholder with exercises already present', async () => {
        buildDom({ exercisesHtml: '<div class="exercise-card"></div>' });
        await nextTick();
        fireMock.mockResolvedValue({ isConfirmed: true });

        selectRoutine('');
        await nextTick();

        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(document.getElementById('exercises-list').innerHTML).toBe('');
    });

    it('reverts to the previous routine when the confirmation is cancelled', async () => {
        buildDom();
        await nextTick();

        selectRoutine('routine-1');
        await nextTick();
        await nextTick();

        fireMock.mockResolvedValue({ isConfirmed: false });
        selectRoutine('');
        await nextTick();

        expect(document.querySelector('select').value).toBe('routine-1');
        expect(document.getElementById('exercises-list').children.length).toBe(1);
    });
});
