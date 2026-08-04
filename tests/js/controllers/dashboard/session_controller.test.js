import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import SessionController from '../../../../assets/controllers/dashboard/session_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('dashboard--session controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="dashboard--session"
                 data-dashboard--session-last-value='{"sessions":1,"sessionsLabel":"Séance","exercises":5,"exercisesLabel":"Exercices","sets":20,"setsLabel":"Séries","reps":180,"prCount":2,"prLabel":"2 PR battus","repsRecordCount":1,"repsRecordLabel":"+ 1 record de reps"}'
                 data-dashboard--session-week-value='{"sessions":3,"sessionsLabel":"Séances","exercises":12,"exercisesLabel":"Exercices","sets":48,"setsLabel":"Séries","reps":410,"prCount":0,"prLabel":"Aucun PR cette fois, continue comme ça !","repsRecordCount":0,"repsRecordLabel":""}'
                 data-dashboard--session-month-value='{"sessions":10,"sessionsLabel":"Séances","exercises":1,"exercisesLabel":"Exercice","sets":110,"setsLabel":"Séries","reps":900,"prCount":5,"prLabel":"5 PR battus","repsRecordCount":3,"repsRecordLabel":"+ 3 records de reps"}'>
                <button data-dashboard--session-target="tab" data-filter="last"
                        data-action="click->dashboard--session#switchFilter" class="dashboard-tab-active"></button>
                <button data-dashboard--session-target="tab" data-filter="week"
                        data-action="click->dashboard--session#switchFilter" class="dashboard-tab-inactive"></button>

                <span data-dashboard--session-target="sessions"></span>
                <span data-dashboard--session-target="sessionsLabel"></span>
                <span data-dashboard--session-target="exercises"></span>
                <span data-dashboard--session-target="exercisesLabel"></span>
                <span data-dashboard--session-target="sets"></span>
                <span data-dashboard--session-target="setsLabel"></span>
                <span data-dashboard--session-target="reps"></span>
                <span data-dashboard--session-target="prIcon">🏆</span>
                <span data-dashboard--session-target="prLabel"></span>
                <div data-dashboard--session-target="repsRecordRow">
                    <span data-dashboard--session-target="repsRecordLabel"></span>
                </div>
            </div>
        `;

        application = Application.start();
        application.register('dashboard--session', SessionController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('displays the "last" stats and PR label on connect', async () => {
        await nextTick();

        expect(document.querySelector('[data-dashboard--session-target="sessions"]').textContent).toBe('1');
        expect(document.querySelector('[data-dashboard--session-target="sessionsLabel"]').textContent).toBe('Séance');
        expect(document.querySelector('[data-dashboard--session-target="exercises"]').textContent).toBe('5');
        expect(document.querySelector('[data-dashboard--session-target="exercisesLabel"]').textContent).toBe('Exercices');
        expect(document.querySelector('[data-dashboard--session-target="sets"]').textContent).toBe('20');
        expect(document.querySelector('[data-dashboard--session-target="setsLabel"]').textContent).toBe('Séries');
        expect(document.querySelector('[data-dashboard--session-target="reps"]').textContent).toBe('180');
        expect(document.querySelector('[data-dashboard--session-target="prLabel"]').textContent).toBe('2 PR battus');
        expect(document.querySelector('[data-dashboard--session-target="prIcon"]').classList.contains('hidden')).toBe(false);
        expect(document.querySelector('[data-dashboard--session-target="repsRecordLabel"]').textContent).toBe('+ 1 record de reps');
        expect(document.querySelector('[data-dashboard--session-target="repsRecordRow"]').classList.contains('hidden')).toBe(false);
    });

    it('updates stats, PR label and active tab when switching to week', async () => {
        await nextTick();

        document.querySelector('[data-filter="week"]').click();

        expect(document.querySelector('[data-dashboard--session-target="sessions"]').textContent).toBe('3');
        expect(document.querySelector('[data-dashboard--session-target="sessionsLabel"]').textContent).toBe('Séances');
        expect(document.querySelector('[data-dashboard--session-target="exercises"]').textContent).toBe('12');
        expect(document.querySelector('[data-dashboard--session-target="exercisesLabel"]').textContent).toBe('Exercices');
        expect(document.querySelector('[data-dashboard--session-target="sets"]').textContent).toBe('48');
        expect(document.querySelector('[data-dashboard--session-target="setsLabel"]').textContent).toBe('Séries');
        expect(document.querySelector('[data-dashboard--session-target="reps"]').textContent).toBe('410');
        expect(document.querySelector('[data-dashboard--session-target="prLabel"]').textContent)
            .toBe('Aucun PR cette fois, continue comme ça !');

        const lastTab = document.querySelector('[data-filter="last"]');
        const weekTab = document.querySelector('[data-filter="week"]');
        expect(lastTab.classList.contains('dashboard-tab-inactive')).toBe(true);
        expect(weekTab.classList.contains('dashboard-tab-active')).toBe(true);
    });

    it('hides the trophy icon when there is no PR for the active filter', async () => {
        await nextTick();

        document.querySelector('[data-filter="week"]').click();

        expect(document.querySelector('[data-dashboard--session-target="prIcon"]').classList.contains('hidden')).toBe(true);
    });

    it('hides the reps record row when there is none for the active filter', async () => {
        await nextTick();

        document.querySelector('[data-filter="week"]').click();

        expect(document.querySelector('[data-dashboard--session-target="repsRecordRow"]').classList.contains('hidden')).toBe(true);
    });
});
