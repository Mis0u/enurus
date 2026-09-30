import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import BannerController from '../../../../assets/controllers/year_in_review/banner_controller.js';

const banner = () => document.querySelector('[data-controller="year-in-review--banner"]');

async function dismiss() {
    document.querySelector('button').click();
    await vi.waitFor(() => expect(global.fetch).toHaveBeenCalled());
    await new Promise((resolve) => setTimeout(resolve, 0));
}

describe('year-in-review--banner controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <aside data-controller="year-in-review--banner"
                   data-year-in-review--banner-url-value="/fr/mes-resumes/2026/bandeau"
                   data-year-in-review--banner-csrf-token-value="token">
                <button type="button" data-action="year-in-review--banner#dismiss"></button>
            </aside>
        `;
        application = Application.start();
        application.register('year-in-review--banner', BannerController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('records the choice as an XHR with its CSRF token, then removes the banner', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true }));

        await dismiss();

        expect(global.fetch).toHaveBeenCalledWith('/fr/mes-resumes/2026/bandeau', expect.objectContaining({
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-Token': 'token' },
        }));
        expect(banner()).toBeNull();
    });

    it('keeps the banner when the server refuses', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: false }));

        await dismiss();

        expect(banner()).not.toBeNull();
    });

    it('keeps the banner when the network fails', async () => {
        vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline')));

        await dismiss();

        expect(banner()).not.toBeNull();
    });
});
