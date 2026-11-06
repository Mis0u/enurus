import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/delete_confirmation.js', () => ({
    confirmAction: vi.fn(),
    sendActionRequest: vi.fn(),
    showDeleteError: vi.fn(),
}));
vi.mock('../../../../assets/utils/toast.js', () => ({ showSuccessToast: vi.fn() }));

const { confirmAction, sendActionRequest, showDeleteError } = await import('../../../../assets/utils/delete_confirmation.js');
const { showSuccessToast } = await import('../../../../assets/utils/toast.js');
const RestoreController = (await import('../../../../assets/controllers/routine/restore_controller.js')).default;

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

describe('routine--restore controller', () => {
    let application;

    beforeEach(async () => {
        vi.clearAllMocks();
        confirmAction.mockResolvedValue(true);
        // Textes néerlandais : un texte écrit en dur dans le controller ferait échouer le test.
        document.body.innerHTML = `
            <div data-controller="routine--restore"
                 data-routine--restore-url-value="/routine/r1/restore"
                 data-routine--restore-csrf-token-value="token"
                 data-routine--restore-error-text-value="Fout"
                 data-routine--restore-name-conflict-text-value="Naam bestaat al">
                <button data-action="routine--restore#confirmRestore"></button>
            </div>
        `;
        application = Application.start();
        application.register('routine--restore', RestoreController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('does not call the server when the popup is cancelled', async () => {
        confirmAction.mockResolvedValue(false);

        document.querySelector('button').click();
        await nextTick();

        expect(sendActionRequest).not.toHaveBeenCalled();
    });

    it('posts the restore request and confirms with a toast', async () => {
        sendActionRequest.mockResolvedValue({ ok: true, data: { success: true, message: 'Hersteld' } });

        document.querySelector('button').click();
        await nextTick();

        expect(sendActionRequest).toHaveBeenCalledWith('/routine/r1/restore', 'POST', 'token');
        expect(showSuccessToast).toHaveBeenCalledWith('Hersteld');
    });

    it('explains a name conflict', async () => {
        sendActionRequest.mockResolvedValue({ ok: false, status: 409 });

        document.querySelector('button').click();
        await nextTick();

        expect(showDeleteError).toHaveBeenCalledWith({ title: 'Naam bestaat al' });
        expect(showSuccessToast).not.toHaveBeenCalled();
    });

    it('shows the generic error otherwise', async () => {
        sendActionRequest.mockResolvedValue({ ok: false, status: 500 });

        document.querySelector('button').click();
        await nextTick();

        expect(showDeleteError).toHaveBeenCalledWith({ title: 'Fout' });
    });
});
