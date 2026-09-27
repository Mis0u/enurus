import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import SectionsController from '../../../../assets/controllers/help/sections_controller.js';

describe('help--sections controller', () => {
    let application;

    const section = (id) => document.getElementById(id);

    const start = () => {
        application = Application.start();
        application.register('help--sections', SectionsController);
    };

    beforeEach(() => {
        Element.prototype.scrollIntoView = () => {};
        document.body.innerHTML = `
            <div data-controller="help--sections">
                <a href="#library" data-action="help--sections#open" data-help--sections-section-param="library">Bibliothèque</a>
                <details id="getting_started" data-help--sections-target="section" open></details>
                <details id="library" data-help--sections-target="section"></details>
            </div>
        `;
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        window.history.replaceState(null, '', '#');
    });

    it('opens the section targeted by the URL hash on arrival', async () => {
        window.history.replaceState(null, '', '#library');
        start();

        await Promise.resolve();

        expect(section('library').open).toBe(true);
    });

    it('opens the section of a summary link when clicked', async () => {
        start();
        await Promise.resolve();

        document.querySelector('a').click();

        expect(section('library').open).toBe(true);
    });

    it('opens the section when the hash changes on the page', async () => {
        start();
        await Promise.resolve();

        window.history.replaceState(null, '', '#library');
        window.dispatchEvent(new HashChangeEvent('hashchange'));

        expect(section('library').open).toBe(true);
    });

    it('ignores a hash that is not a section', async () => {
        window.history.replaceState(null, '', '#unknown');
        start();

        await Promise.resolve();

        expect(section('library').open).toBe(false);
    });
});
