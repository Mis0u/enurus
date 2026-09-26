import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import CardController from '../../../../assets/controllers/exercise/card_controller.js';

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

describe('exercise--card controller', () => {
    let application;

    beforeEach(async () => {
        document.body.innerHTML = `
            <div data-controller="exercise--card">
                <button aria-expanded="false" data-exercise--card-target="toggle" data-action="exercise--card#toggle"></button>
            </div>
        `;
        application = Application.start();
        application.register('exercise--card', CardController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    const card = () => document.querySelector('[data-controller="exercise--card"]');
    const toggle = () => document.querySelector('button');

    it('unfolds the row details on a tap', () => {
        toggle().click();

        expect(card().classList.contains('is-expanded')).toBe(true);
        expect(toggle().getAttribute('aria-expanded')).toBe('true');
    });

    it('folds them back on a second tap', () => {
        toggle().click();
        toggle().click();

        expect(card().classList.contains('is-expanded')).toBe(false);
        expect(toggle().getAttribute('aria-expanded')).toBe('false');
    });
});
