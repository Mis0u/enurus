import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { NoteModalManager } from '../../../../../assets/controllers/workout/create/note_modal_manager.js';

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
        const manager = new NoteModalManager(fakeApplication, '/seance/__ID__/photo');

        // Reproduit le scénario du bug : la modale est ouverte une première fois (date vide,
        // reportValidity() bloque et referme la modale), puis rouverte après correction.
        manager.open();
        manager.open();

        document.getElementById('note-modal-submit').click();
        await new Promise(resolve => setTimeout(resolve, 0));

        expect(global.fetch).toHaveBeenCalledTimes(1);
    });
});
