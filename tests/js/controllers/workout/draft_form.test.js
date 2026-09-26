import { afterEach, describe, expect, it, vi } from 'vitest';
import { readDraftFromForm, restoreDraftFields } from '../../../../assets/controllers/workout/draft_form.js';

function card(index, exerciseId, rowsHtml, { blocked = false, prefilled = false } = {}) {
    return `
        <div data-exercise-index="${index}" data-bodyweight-blocked="${blocked}">
            ${prefilled ? '<div class="js-prefilled-from"></div>' : ''}
            <input type="hidden" name="workout[workoutExercises][${index}][exercise]" value="${exerciseId}">
            <table><tbody class="js-sets-tbody">${rowsHtml}</tbody></table>
        </div>
    `;
}

function weightRepsRow(index, set, weight, reps) {
    return `<tr>
        <td><input name="workout[workoutExercises][${index}][exerciseSets][${set}][weight]" value="${weight}">
            <input type="hidden" name="workout[workoutExercises][${index}][exerciseSets][${set}][position]" value="${set}"></td>
        <td><input name="workout[workoutExercises][${index}][exerciseSets][${set}][reps]" value="${reps}"></td>
    </tr>`;
}

function buildForm({ cards = '', routineVisible = false } = {}) {
    document.body.innerHTML = `
        <div id="root">
            <input id="workout_performedAt" value="2026-09-26">
            <input id="workout_duration" value="75">
            <button type="button" data-session-type-target="routine"></button>
            <div id="routine-selector" class="${routineVisible ? '' : 'hidden'}">
                <select id="workout-routine">
                    <option value=""></option>
                    <option value="routine-1" selected>Push</option>
                </select>
            </div>
            <div id="exercises-list">${cards}</div>
        </div>
    `;

    return document.getElementById('root');
}

describe('workout draft form', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('reads the typed sets of every exercise, in the displayed order', () => {
        const root = buildForm({
            cards: card(1, 'ex-b', weightRepsRow(1, 0, '42.5', '8') + weightRepsRow(1, 1, '', '6'))
                + card(0, 'ex-a', weightRepsRow(0, 0, '20', '')),
        });

        expect(readDraftFromForm(root).exercises).toEqual([
            { exerciseId: 'ex-b', prefilled: false, sets: [
                { weight: 42.5, reps: 8, duration: null, distance: null },
                { weight: null, reps: 6, duration: null, distance: null },
            ] },
            { exerciseId: 'ex-a', prefilled: false, sets: [{ weight: 20, reps: null, duration: null, distance: null }] },
        ]);
    });

    it('keeps no set for a card blocked by a missing bodyweight', () => {
        const root = buildForm({ cards: card(0, 'ex-a', weightRepsRow(0, 0, '', ''), { blocked: true }) });

        expect(readDraftFromForm(root).exercises).toEqual([{ exerciseId: 'ex-a', prefilled: false, sets: [] }]);
    });

    it('remembers a card still showing its carry-over notice', () => {
        const root = buildForm({ cards: card(0, 'ex-a', weightRepsRow(0, 0, '80', '10'), { prefilled: true }) });

        expect(readDraftFromForm(root).exercises[0].prefilled).toBe(true);
    });

    it('reads the date, the duration and the chosen routine', () => {
        const root = buildForm({ routineVisible: true });

        expect(readDraftFromForm(root)).toMatchObject({ date: '2026-09-26', duration: '75', routineId: 'routine-1' });
    });

    it('ignores the routine of a free session', () => {
        const root = buildForm({ routineVisible: false });

        expect(readDraftFromForm(root).routineId).toBe('');
    });

    it('restores the date through the date picker, and the duration', () => {
        const root = buildForm();
        const dateInput = root.querySelector('#workout_performedAt');
        dateInput._flatpickr = { setDate: vi.fn() };

        restoreDraftFields(root, { date: '2026-09-20', duration: '45', routineId: '' });

        expect(dateInput._flatpickr.setDate).toHaveBeenCalledWith('2026-09-20', false);
        expect(root.querySelector('#workout_duration').value).toBe('45');
    });

    it('switches to the routine session type when the draft has a routine', () => {
        const root = buildForm();
        root.querySelector('#workout-routine').value = '';
        const routineButton = root.querySelector('[data-session-type-target="routine"]');
        const click = vi.fn();
        routineButton.addEventListener('click', click);

        restoreDraftFields(root, { date: '', duration: '', routineId: 'routine-1' });

        expect(click).toHaveBeenCalled();
        expect(root.querySelector('#workout-routine').value).toBe('routine-1');
    });

    it('ignores a routine that no longer exists', () => {
        const root = buildForm();
        root.querySelector('#workout-routine').value = '';
        const click = vi.fn();
        root.querySelector('[data-session-type-target="routine"]').addEventListener('click', click);

        restoreDraftFields(root, { date: '', duration: '', routineId: 'deleted-routine' });

        expect(click).not.toHaveBeenCalled();
    });
});
