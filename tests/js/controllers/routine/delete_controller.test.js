import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/delete_confirmation.js', () => ({
    confirmDeletion: vi.fn().mockResolvedValue(true),
    sendDeleteRequest: vi.fn().mockResolvedValue({ ok: true, data: { success: true, message: 'Supprimée' } }),
    showDeleteError: vi.fn(),
}));
vi.mock('../../../../assets/utils/toast.js', () => ({ showSuccessToast: vi.fn() }));

const DeleteController = (await import('../../../../assets/controllers/routine/delete_controller.js')).default;

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

// Libellés néerlandais : un texte écrit en dur dans le controller ferait échouer le test.
const COUNT_LABELS = ['Geen routines', 'Eén routine', 'Twee routines'];

function routineCard(id) {
    return `
        <div class="routine-card" id="${id}">
            <div data-controller="routine--delete" data-routine--delete-url-value="/routine/${id}">
                <button data-action="routine--delete#confirmDelete"></button>
            </div>
        </div>
    `;
}

describe('routine--delete controller', () => {
    let application;

    beforeEach(async () => {
        document.body.innerHTML = `
            <p data-routine-count="2" data-routine-count-labels='${JSON.stringify(COUNT_LABELS)}'>2 routines</p>
            <div class="routines-grid">${routineCard('r1')}${routineCard('r2')}</div>
        `;
        application = Application.start();
        application.register('routine--delete', DeleteController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('updates the counter with the translated label after a deletion', async () => {
        document.querySelector('#r1 button').click();
        await nextTick();

        const counter = document.querySelector('[data-routine-count]');
        expect(counter.textContent).toBe('Eén routine');
        expect(counter.dataset.routineCount).toBe('1');
    });
});
