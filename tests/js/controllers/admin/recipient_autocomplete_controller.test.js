import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import RecipientAutocompleteController from '../../../../assets/controllers/admin/recipient_autocomplete_controller.js';

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="admin--recipient-autocomplete"
             data-admin--recipient-autocomplete-search-url-value="/admin/recherche-destinataire"
             data-admin--recipient-autocomplete-locale-label-value="Locale :">
            <input data-admin--recipient-autocomplete-target="query"
                   data-action="input->admin--recipient-autocomplete#search">
            <input type="hidden" data-admin--recipient-autocomplete-target="hidden">
            <div data-admin--recipient-autocomplete-target="results" hidden></div>
            <span data-admin--recipient-autocomplete-target="localeDisplay"></span>
        </div>
    `;
}

describe('admin--recipient-autocomplete controller', () => {
    let application;

    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] });
        buildDom();
        application = Application.start();
        application.register('admin--recipient-autocomplete', RecipientAutocompleteController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    function query() {
        return document.querySelector('[data-admin--recipient-autocomplete-target="query"]');
    }

    it('clears the previous selection immediately when typing again', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => [] }));

        document.querySelector('[data-admin--recipient-autocomplete-target="hidden"]').value = 'user-1';
        document.querySelector('[data-admin--recipient-autocomplete-target="localeDisplay"]').textContent = 'Locale : fr';

        query().value = 'a';
        query().dispatchEvent(new Event('input'));

        expect(document.querySelector('[data-admin--recipient-autocomplete-target="hidden"]').value).toBe('');
        expect(document.querySelector('[data-admin--recipient-autocomplete-target="localeDisplay"]').textContent).toBe('');
    });

    it('does not query the API for an empty search and hides the results', async () => {
        vi.stubGlobal('fetch', vi.fn());

        query().value = '   ';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(250);

        expect(global.fetch).not.toHaveBeenCalled();
        expect(document.querySelector('[data-admin--recipient-autocomplete-target="results"]').hidden).toBe(true);
    });

    it('debounces the search, firing only once after 250ms of inactivity', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => [] }));

        query().value = 'm';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(100);
        query().value = 'mi';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(100);
        query().value = 'mic';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(250);

        expect(global.fetch).toHaveBeenCalledTimes(1);
        expect(global.fetch).toHaveBeenCalledWith('/admin/recherche-destinataire?query=mic');
    });

    it('renders one result button per user returned by the API', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => [
                { id: 'user-1', email: 'a@test.com', locale: 'fr' },
                { id: 'user-2', email: 'b@test.com', locale: 'en' },
            ],
        }));

        query().value = 'test';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(250);

        const buttons = document.querySelectorAll('[data-admin--recipient-autocomplete-target="results"] button');
        expect(buttons).toHaveLength(2);
        expect(document.querySelector('[data-admin--recipient-autocomplete-target="results"]').hidden).toBe(false);
    });

    it('select() fills the hidden id, the query field and the locale display, then clears the results', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => [{ id: 'user-1', email: 'a@test.com', locale: 'fr' }],
        }));

        query().value = 'test';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(250);

        document.querySelector('[data-admin--recipient-autocomplete-target="results"] button').click();

        expect(document.querySelector('[data-admin--recipient-autocomplete-target="hidden"]').value).toBe('user-1');
        expect(query().value).toBe('a@test.com');
        expect(document.querySelector('[data-admin--recipient-autocomplete-target="localeDisplay"]').textContent).toBe('Locale : fr');
        expect(document.querySelectorAll('[data-admin--recipient-autocomplete-target="results"] button')).toHaveLength(0);
    });

    it('hides the results on a non-ok API response', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        query().value = 'test';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(250);

        expect(document.querySelector('[data-admin--recipient-autocomplete-target="results"]').hidden).toBe(true);
    });

    it('hides the results on a network error', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('network down')));

        query().value = 'test';
        query().dispatchEvent(new Event('input'));
        await vi.advanceTimersByTimeAsync(250);

        expect(document.querySelector('[data-admin--recipient-autocomplete-target="results"]').hidden).toBe(true);
    });
});
