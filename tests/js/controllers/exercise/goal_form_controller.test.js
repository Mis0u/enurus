import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import GoalFormController from '../../../../assets/controllers/exercise/goal_form_controller.js';

describe('exercise--goal-form controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="exercise--goal-form">
                <button data-action="click->exercise--goal-form#show">Modifier</button>
                <div class="hidden" data-exercise--goal-form-target="form"></div>
            </div>
        `;

        application = Application.start();
        application.register('exercise--goal-form', GoalFormController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('keeps the form hidden until "Modifier" is clicked', () => {
        expect(document.querySelector('[data-exercise--goal-form-target="form"]').classList.contains('hidden')).toBe(true);
    });

    it('reveals the form when "Modifier" is clicked', () => {
        document.querySelector('button').click();

        expect(document.querySelector('[data-exercise--goal-form-target="form"]').classList.contains('hidden')).toBe(false);
    });
});
