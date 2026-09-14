import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import GoalController from '../../../../assets/controllers/dashboard/goal_controller.js';

describe('dashboard--goal controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <div data-controller="dashboard--goal">
                <button data-dashboard--goal-target="tab" data-filter="current"
                        data-action="click->dashboard--goal#switchFilter" class="dashboard-tab-active"></button>
                <button data-dashboard--goal-target="tab" data-filter="achieved"
                        data-action="click->dashboard--goal#switchFilter" class="dashboard-tab-inactive"></button>

                <div data-dashboard--goal-target="panel" data-filter="current" class="flex"></div>
                <div data-dashboard--goal-target="panel" data-filter="achieved" class="hidden"></div>
            </div>
        `;

        application = Application.start();
        application.register('dashboard--goal', GoalController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('shows the "achieved" panel and hides the "current" one when switching tabs', () => {
        document.querySelector('[data-filter="achieved"][data-dashboard--goal-target="tab"]').click();

        const currentPanel = document.querySelector('[data-dashboard--goal-target="panel"][data-filter="current"]');
        const achievedPanel = document.querySelector('[data-dashboard--goal-target="panel"][data-filter="achieved"]');

        expect(currentPanel.classList.contains('hidden')).toBe(true);
        expect(currentPanel.classList.contains('flex')).toBe(false);
        expect(achievedPanel.classList.contains('hidden')).toBe(false);
        expect(achievedPanel.classList.contains('flex')).toBe(true);
    });

    it('marks the active tab and un-marks the previous one', () => {
        const currentTab = document.querySelector('[data-filter="current"][data-dashboard--goal-target="tab"]');
        const achievedTab = document.querySelector('[data-filter="achieved"][data-dashboard--goal-target="tab"]');

        achievedTab.click();

        expect(currentTab.classList.contains('dashboard-tab-active')).toBe(false);
        expect(achievedTab.classList.contains('dashboard-tab-active')).toBe(true);
    });
});
