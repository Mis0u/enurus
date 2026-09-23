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

const UnlockedController = (await import('../../../../assets/controllers/badge/unlocked_controller.js')).default;

describe('badge--unlocked controller', () => {
    let application;
    const assignMock = vi.fn();

    beforeEach(() => {
        vi.clearAllMocks();
        vi.stubGlobal('location', { ...window.location, assign: assignMock });
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    function connectWith({ legend = false } = {}) {
        document.body.innerHTML = `
            <div data-controller="badge--unlocked"
                 data-badge--unlocked-title-value="Nouveau badge !"
                 data-badge--unlocked-text-value="Tu as débloqué le badge « 100 séances »."
                 data-badge--unlocked-see-all-url-value="/fr/mes-badges"
                 data-badge--unlocked-see-all-label-value="Voir mes badges"
                 data-badge--unlocked-close-label-value="Continuer"
                 ${legend ? 'data-badge--unlocked-legend-value="true"' : ''}>
                <template data-badge--unlocked-target="content"><svg class="badge-svg"></svg></template>
            </div>
        `;

        application = Application.start();
        application.register('badge--unlocked', UnlockedController);
    }

    async function flush() {
        for (let i = 0; i < 5; i++) {
            await Promise.resolve();
        }
    }

    it('shows the rendered badge, the text and both buttons', async () => {
        fireMock.mockResolvedValue({ isConfirmed: false });
        connectWith();
        await flush();

        expect(confettiMock).toHaveBeenCalledTimes(1);
        const options = fireMock.mock.calls[0][0];
        expect(options.title).toBe('Nouveau badge !');
        expect(options.confirmButtonText).toBe('Voir mes badges');
        expect(options.cancelButtonText).toBe('Continuer');
        expect(options.html.querySelector('svg.badge-svg')).not.toBeNull();
        expect(options.html.textContent).toContain('100 séances');
    });

    it('goes to the badges page when "see all" is confirmed', async () => {
        fireMock.mockResolvedValue({ isConfirmed: true });
        connectWith();
        await flush();

        expect(assignMock).toHaveBeenCalledWith('/fr/mes-badges');
    });

    it('stays on the page when the popup is dismissed', async () => {
        fireMock.mockResolvedValue({ isConfirmed: false });
        connectWith();
        await flush();

        expect(assignMock).not.toHaveBeenCalled();
    });

    it('throws brand-coloured confetti for the legend badge', async () => {
        fireMock.mockResolvedValue({ isConfirmed: false });
        connectWith({ legend: true });
        await flush();

        expect(confettiMock).toHaveBeenCalledWith(expect.objectContaining({ colors: expect.arrayContaining(['#f43f5e', '#06b6d4']) }));
    });
});
