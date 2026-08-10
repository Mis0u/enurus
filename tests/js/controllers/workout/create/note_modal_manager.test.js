import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const fireMock = vi.fn();

vi.mock('sweetalert2', () => ({
    default: {
        fire: fireMock,
    },
}));

const { NoteModalManager } = await import('../../../../../assets/controllers/workout/create/note_modal_manager.js');

function buildDom(dateValue) {
    document.body.innerHTML = `
        <form action="/enregistre-seance">
            <input type="date" name="workout[performedAt]" required value="${dateValue}">
            <input type="hidden" name="workout[_token]" value="token">
            <input type="hidden" id="workout_note" name="workout[note]">
        </form>
        <div id="note-modal" class="hidden">
            <textarea id="workout-note-input"></textarea>
            <button type="button" id="note-modal-back">Back</button>
            <button type="button" id="note-modal-submit">Submit</button>
        </div>
    `;
}

describe('NoteModalManager', () => {
    let fakeApplication;

    beforeEach(() => {
        fakeApplication = { getControllerForElementAndIdentifier: () => null };
        global.fetch = vi.fn().mockResolvedValue({
            ok: false,
            json: async () => ({ error: 'validation failed' }),
        });
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('submits only once even after the modal has been reopened (blank-date retry scenario)', async () => {
        buildDom('2026-01-29');
        const manager = new NoteModalManager(fakeApplication, '/seance/__ID__/photo', 'Save failed', 'Try again');

        // Reproduit le scénario du bug : la modale est ouverte une première fois (date vide,
        // reportValidity() bloque et referme la modale), puis rouverte après correction.
        manager.open();
        manager.open();

        document.getElementById('note-modal-submit').click();
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });

    it('disables the submit button during submission so a second click cannot create a duplicate workout', async () => {
        buildDom('2026-01-29');
        let resolveFetch;
        global.fetch = vi.fn().mockReturnValue(new Promise(resolve => {
            resolveFetch = resolve;
        }));

        const manager = new NoteModalManager(fakeApplication, '/seance/__ID__/photo', 'Save failed', 'Try again');
        manager.open();

        const submitBtn = document.getElementById('note-modal-submit');
        submitBtn.click();
        await Promise.resolve();

        expect(submitBtn.disabled).toBe(true);

        // Impatience de l'utilisateur : re-clique pendant que la première requête est toujours en cours.
        submitBtn.click();
        await Promise.resolve();

        expect(global.fetch).toHaveBeenCalledTimes(1);

        resolveFetch({ ok: false, json: async () => ({ error: 'validation failed' }) });
        await new Promise(resolve => setTimeout(resolve, 0));
    });

    it('shows an error toast when the server rejects the submission', async () => {
        buildDom('2026-01-29');
        const manager = new NoteModalManager(fakeApplication, '/seance/__ID__/photo', 'Save failed', 'Try again');

        manager.open();
        document.getElementById('note-modal-submit').click();
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(fireMock).toHaveBeenCalledTimes(1);
        expect(fireMock).toHaveBeenCalledWith(expect.objectContaining({
            title: 'Save failed',
            text: 'Try again',
        }));
    });
});
