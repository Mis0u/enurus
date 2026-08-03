import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn(() => Promise.resolve({ isConfirmed: true }));
vi.mock('sweetalert2', () => ({ default: { fire: fireMock } }));

const DateController = (await import('../../../assets/controllers/date_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom({ excludeId = '' } = {}) {
    document.body.innerHTML = `
        <input
            type="text"
            data-controller="date"
            data-date-check-url-value="/check-date"
            data-date-message-value="Tu as déjà une séance ce jour."
            data-date-confirm-button-value="OK, noté !"
            ${excludeId ? `data-date-exclude-id-value="${excludeId}"` : ''}
        >
    `;
}

describe('date controller', () => {
    let application;

    beforeEach(() => {
        fireMock.mockClear();
        application = Application.start();
        application.register('date', DateController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('does nothing when the date field is empty', async () => {
        buildDom();
        await nextTick();

        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        document.querySelector('input').dispatchEvent(new Event('change'));
        await nextTick();

        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('shows a confirmation modal with returnFocus disabled when a workout already exists on that date', async () => {
        buildDom();
        await nextTick();

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ exists: true, message: 'Tu as déjà une séance ce jour.' }),
        }));

        const input = document.querySelector('input');
        input.value = '2026-01-15';

        input.dispatchEvent(new Event('change'));
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenCalledTimes(1);
        // returnFocus doit être désactivé, sinon SweetAlert2 remet le focus sur le champ à la
        // fermeture et flatpickr rouvre aussitôt le calendrier (cf. bug — un blur() après coup ne
        // suffit pas, la restauration de focus a lieu après la résolution de Swal.fire()).
        expect(fireMock.mock.calls[0][0]).toMatchObject({ returnFocus: false });
    });

    it('does not show a modal when no workout exists on that date', async () => {
        buildDom();
        await nextTick();

        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ exists: false }),
        }));

        const input = document.querySelector('input');
        input.value = '2026-01-15';
        input.dispatchEvent(new Event('change'));
        await nextTick();
        await nextTick();

        expect(fireMock).not.toHaveBeenCalled();
    });

    it('includes excludeId in the request URL when set, to ignore the workout being edited', async () => {
        buildDom({ excludeId: 'abc-123' });
        await nextTick();

        const fetchMock = vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ exists: false }),
        });
        vi.stubGlobal('fetch', fetchMock);

        const input = document.querySelector('input');
        input.value = '2026-01-15';
        input.dispatchEvent(new Event('change'));
        await nextTick();
        await nextTick();

        const calledUrl = new URL(fetchMock.mock.calls[0][0]);
        expect(calledUrl.searchParams.get('date')).toBe('2026-01-15');
        expect(calledUrl.searchParams.get('excludeId')).toBe('abc-123');
    });
});
