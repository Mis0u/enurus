import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

vi.mock('../../../../assets/utils/toast.js', () => ({
    showSuccessToast: vi.fn(),
    showErrorToast: vi.fn(),
}));

const { showErrorToast } = await import('../../../../assets/utils/toast.js');
const DataExportController = (await import('../../../../assets/controllers/settings/data_export_controller.js')).default;

const ERROR_MESSAGE = 'Une erreur est survenue, réessaie plus tard';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function fileResponse(filename) {
    return {
        ok: true,
        redirected: false,
        headers: new Headers({ 'Content-Disposition': `attachment; filename=${filename}` }),
        blob: () => Promise.resolve(new Blob(['Date;Heure'])),
    };
}

function link() {
    return document.querySelector('a');
}

// `click()` est intercepté plus bas pour le lien d'enregistrement : le clic utilisateur passe par un événement.
function clickExport() {
    link().dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
}

describe('settings--data-export controller', () => {
    let application;
    let savedFilenames;

    beforeEach(() => {
        vi.clearAllMocks();
        savedFilenames = [];

        URL.createObjectURL = vi.fn(() => 'blob:export');
        URL.revokeObjectURL = vi.fn();
        // Le lien créé pour enregistrer le fichier : on note son nom au lieu de naviguer (jsdom).
        vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function () {
            savedFilenames.push(this.download);
        });

        document.body.innerHTML = `
            <ul data-controller="settings--data-export"
                data-settings--data-export-error-message-value="${ERROR_MESSAGE}">
                <li><a href="/fr/reglages/export/csv" download data-action="settings--data-export#download">CSV</a></li>
            </ul>
        `;

        application = Application.start();
        application.register('settings--data-export', DataExportController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
        vi.restoreAllMocks();
    });

    it('shows a spinner and marks the link busy while the file is being prepared', async () => {
        vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));

        clickExport();
        await nextTick();

        expect(link().querySelector('[data-submit-spinner]')).not.toBeNull();
        expect(link().getAttribute('aria-busy')).toBe('true');
        expect(link().getAttribute('aria-disabled')).toBe('true');
    });

    it('saves the file under the name given by the server, then frees the link', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve(fileResponse('enurus-workouts-2026-10-13.csv'))));

        clickExport();
        await nextTick();
        await nextTick();

        expect(fetch).toHaveBeenCalledWith(link().href);
        expect(savedFilenames).toEqual(['enurus-workouts-2026-10-13.csv']);
        expect(link().querySelector('[data-submit-spinner]')).toBeNull();
        expect(link().hasAttribute('aria-busy')).toBe(false);
    });

    it('shows the translated error toast when the server fails, then frees the link', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: false, status: 500, redirected: false })));

        clickExport();
        await nextTick();

        expect(showErrorToast).toHaveBeenCalledWith(ERROR_MESSAGE);
        expect(savedFilenames).toEqual([]);
        expect(link().querySelector('[data-submit-spinner]')).toBeNull();
    });

    it('shows the error toast when the network fails', async () => {
        vi.stubGlobal('fetch', vi.fn(() => Promise.reject(new TypeError('Failed to fetch'))));

        clickExport();
        await nextTick();

        expect(showErrorToast).toHaveBeenCalledWith(ERROR_MESSAGE);
    });

    it('ignores new clicks while an export is already running', async () => {
        vi.stubGlobal('fetch', vi.fn(() => new Promise(() => {})));

        clickExport();
        clickExport();
        await nextTick();

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('goes to the login page instead of saving it when the session has expired', async () => {
        const assign = vi.fn();
        vi.stubGlobal('location', { assign });
        vi.stubGlobal('fetch', vi.fn(() => Promise.resolve({ ok: true, redirected: true, url: 'http://localhost/fr/connexion' })));

        clickExport();
        await nextTick();

        expect(assign).toHaveBeenCalledWith('http://localhost/fr/connexion');
        expect(savedFilenames).toEqual([]);
    });
});
