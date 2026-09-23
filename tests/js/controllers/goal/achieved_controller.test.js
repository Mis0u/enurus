import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn();
const confettiMock = vi.fn();

vi.mock('sweetalert2', () => ({
    default: {
        fire: fireMock,
    },
}));

vi.mock('canvas-confetti', () => ({
    default: confettiMock,
}));

const AchievedController = (await import('../../../../assets/controllers/goal/achieved_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('goal--achieved controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();
        vi.useFakeTimers();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.useRealTimers();
    });

    function connectWith(achievements) {
        document.body.innerHTML = `
            <div data-controller="goal--achieved"
                 data-goal--achieved-achievements-value='${JSON.stringify(achievements)}'></div>
        `;

        application = Application.start();
        application.register('goal--achieved', AchievedController);
    }

    it('fires confetti and a success alert for a single achievement', async () => {
        connectWith([
            { title: 'Objectif atteint ! 🎉', text: 'Tu as atteint ton objectif sur Squat : 100 kg !', confirmButtonText: 'Continuer' },
        ]);

        await vi.runAllTimersAsync();

        expect(confettiMock).toHaveBeenCalledTimes(1);
        expect(fireMock).toHaveBeenCalledWith(expect.objectContaining({
            icon: 'success',
            title: 'Objectif atteint ! 🎉',
            text: 'Tu as atteint ton objectif sur Squat : 100 kg !',
            confirmButtonText: 'Continuer',
        }));
    });

    it('celebrates each achievement once, one popup after the other', async () => {
        connectWith([
            { title: 'Objectif atteint ! 🎉', text: 'Squat : 100 kg !', confirmButtonText: 'Continuer' },
            { title: 'Objectif atteint ! 🎉', text: 'Développé couché : 80 kg !', confirmButtonText: 'Continuer' },
        ]);

        await vi.runAllTimersAsync();

        expect(confettiMock).toHaveBeenCalledTimes(2);
        expect(fireMock).toHaveBeenCalledTimes(2);
    });

    it('does nothing when there is no achievement to display', async () => {
        connectWith([]);

        await vi.runAllTimersAsync();

        expect(confettiMock).not.toHaveBeenCalled();
        expect(fireMock).not.toHaveBeenCalled();
    });
});
