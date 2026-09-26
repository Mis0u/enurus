import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import DraftController from '../../../../assets/controllers/workout/draft_controller.js';
import { loadDraft, saveDraft } from '../../../../assets/controllers/workout/draft_storage.js';

const USER_ID = 'user-1';
const CARD_HTML = '<div data-exercise-index="__EXERCISE_INDEX__"></div>';

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="workout--draft"
             data-workout--draft-user-id-value="${USER_ID}"
             data-workout--draft-block-url-value="/draft-block">
            <div data-workout--draft-target="banner" hidden></div>
            <input id="workout_performedAt" value="">
            <input id="workout_duration" value="">
            <div id="exercises-list"></div>
        </div>
    `;
}

function addTypedCard() {
    document.getElementById('exercises-list').innerHTML = `
        <div data-exercise-index="0">
            <input type="hidden" name="w[0][exercise]" value="ex-1">
            <table><tbody class="js-sets-tbody"><tr>
                <td><input name="w[0][exerciseSets][0][weight]" value="80"></td>
                <td><input name="w[0][exerciseSets][0][reps]" value="10"></td>
            </tr></tbody></table>
        </div>
    `;
}

function sentExercises() {
    return JSON.parse(fetch.mock.calls[0][1].body).exercises;
}

describe('workout--draft controller', () => {
    let application;
    let selectedEvents;
    const recordSelected = (event) => selectedEvents.push(event.detail.htmls);

    beforeEach(() => {
        selectedEvents = [];
        window.addEventListener('exercise:selected', recordSelected);
        window.history.replaceState(null, '', '/fr/enregistre-seance');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ htmls: [CARD_HTML] }),
        }));

        application = Application.start();
        application.register('workout--draft', DraftController);
    });

    afterEach(() => {
        application.stop();
        window.removeEventListener('exercise:selected', recordSelected);
        document.body.innerHTML = '';
        localStorage.clear();
        vi.unstubAllGlobals();
        vi.useRealTimers();
    });

    it('does nothing without a draft', async () => {
        buildDom();
        await nextTick();

        expect(fetch).not.toHaveBeenCalled();
        expect(document.querySelector('[data-workout--draft-target="banner"]').hidden).toBe(true);
    });

    it('restores the draft cards and fields, and shows the banner', async () => {
        saveDraft(USER_ID, { date: '', duration: '50', routineId: '', exercises: [{ exerciseId: 'ex-1', sets: [] }] });

        buildDom();
        await nextTick();
        await nextTick();

        expect(sentExercises()).toEqual([{ exerciseId: 'ex-1', sets: [] }]);
        expect(selectedEvents).toEqual([[CARD_HTML]]);
        expect(document.getElementById('workout_duration').value).toBe('50');
        expect(document.querySelector('[data-workout--draft-target="banner"]').hidden).toBe(false);
    });

    it('adds the exercise just created at the end, then drops it from the URL', async () => {
        saveDraft(USER_ID, { date: '', duration: '', routineId: '', exercises: [{ exerciseId: 'ex-1', sets: [] }] });
        window.history.replaceState(null, '', '/fr/enregistre-seance?addedExercise=ex-new');

        buildDom();
        await nextTick();

        expect(sentExercises()).toEqual([{ exerciseId: 'ex-1', sets: [] }, { exerciseId: 'ex-new', sets: [] }]);
        expect(window.location.search).toBe('');
    });

    it('drops the cards fetched for a page Turbo has already replaced', async () => {
        saveDraft(USER_ID, { date: '', duration: '', routineId: '', exercises: [{ exerciseId: 'ex-1', sets: [] }] });

        buildDom();
        document.body.innerHTML = '';
        await nextTick();
        await nextTick();

        expect(selectedEvents).toEqual([]);
    });

    it('adds the exercise just created without any banner when there was no draft', async () => {
        window.history.replaceState(null, '', '/fr/enregistre-seance?addedExercise=ex-new');

        buildDom();
        await nextTick();
        await nextTick();

        expect(sentExercises()).toEqual([{ exerciseId: 'ex-new', sets: [] }]);
        expect(document.querySelector('[data-workout--draft-target="banner"]').hidden).toBe(true);
    });

    it('saves the typed sets shortly after a change', async () => {
        buildDom();
        await nextTick();
        vi.useFakeTimers();

        addTypedCard();
        window.dispatchEvent(new CustomEvent('workout:changed'));
        vi.advanceTimersByTime(500);

        expect(loadDraft(USER_ID).exercises).toEqual([
            { exerciseId: 'ex-1', prefilled: false, sets: [{ weight: 80, reps: 10, duration: null, distance: null }] },
        ]);
    });

    it('saves at once when the app goes to the background', async () => {
        buildDom();
        await nextTick();

        addTypedCard();
        window.dispatchEvent(new Event('pagehide'));

        expect(loadDraft(USER_ID)).not.toBeNull();
    });

    it('drops the draft once the workout has no exercise left', async () => {
        saveDraft(USER_ID, { date: '', duration: '', routineId: '', exercises: [{ exerciseId: 'ex-1', sets: [] }] });
        buildDom();
        await nextTick();
        await nextTick();

        document.getElementById('exercises-list').innerHTML = '';
        window.dispatchEvent(new Event('pagehide'));

        expect(loadDraft(USER_ID)).toBeNull();
    });

    it('drops the draft for good once the workout is saved', async () => {
        buildDom();
        await nextTick();
        addTypedCard();
        window.dispatchEvent(new Event('pagehide'));

        window.dispatchEvent(new CustomEvent('workout:saved'));
        window.dispatchEvent(new Event('pagehide'));

        expect(loadDraft(USER_ID)).toBeNull();
    });
});
