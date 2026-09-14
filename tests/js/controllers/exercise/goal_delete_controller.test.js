import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn();

vi.mock('sweetalert2', () => ({
    default: {
        fire: fireMock,
    },
}));

const GoalDeleteController = (await import('../../../../assets/controllers/exercise/goal_delete_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

describe('exercise--goal-delete controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();

        document.body.innerHTML = `
            <button data-controller="exercise--goal-delete"
                    data-exercise--goal-delete-url-value="/bibliotheque/objectif/abc/supprimer"
                    data-exercise--goal-delete-csrf-token-value="token"
                    data-exercise--goal-delete-confirm-title-value="Supprimer l'objectif"
                    data-exercise--goal-delete-confirm-text-value="Veux-tu supprimer cet objectif ?"
                    data-exercise--goal-delete-confirm-button-value="Supprimer"
                    data-exercise--goal-delete-cancel-button-value="Annuler"
                    data-exercise--goal-delete-error-text-value="Une erreur est survenue."
                    data-action="click->exercise--goal-delete#confirmDelete">
                Supprimer
            </button>
        `;

        application = Application.start();
        application.register('exercise--goal-delete', GoalDeleteController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('does not call the delete endpoint when the confirmation is dismissed', async () => {
        fireMock.mockResolvedValue({ isConfirmed: false });
        vi.stubGlobal('fetch', vi.fn());

        document.querySelector('button').click();
        await nextTick();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('sends a DELETE request with the XHR and CSRF headers once confirmed', async () => {
        fireMock.mockResolvedValue({ isConfirmed: true });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ success: true, message: 'Objectif supprimé.' }),
        }));

        document.querySelector('button').click();
        await nextTick();
        await nextTick();

        expect(global.fetch).toHaveBeenCalledWith('/bibliotheque/objectif/abc/supprimer', expect.objectContaining({
            method: 'DELETE',
            headers: expect.objectContaining({
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': 'token',
            }),
        }));
    });

    it('shows an error alert when the server rejects the deletion', async () => {
        fireMock.mockResolvedValue({ isConfirmed: true });
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        document.querySelector('button').click();
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenLastCalledWith(expect.objectContaining({
            icon: 'error',
            text: 'Une erreur est survenue.',
        }));
    });
});
