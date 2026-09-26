import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import MobileMoreMenuController from '../../../assets/controllers/mobile_more_menu_controller.js';

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

// jsdom n'implémente pas l'ouverture modale d'un <dialog> : simulée par l'attribut `open` et
// l'événement `close` natif.
function stubDialog() {
    HTMLDialogElement.prototype.showModal = function showModal() {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function close() {
        this.open = false;
        this.dispatchEvent(new Event('close'));
    };
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="mobile-more-menu">
            <button aria-expanded="false" data-mobile-more-menu-target="button" data-action="mobile-more-menu#open"></button>
            <dialog data-mobile-more-menu-target="dialog" data-action="click->mobile-more-menu#closeOnBackdrop">
                <div class="content">
                    <button class="close" data-action="mobile-more-menu#close"></button>
                </div>
            </dialog>
        </div>
    `;
}

const dialog = () => document.querySelector('dialog');
const moreButton = () => document.querySelector('[data-mobile-more-menu-target="button"]');

describe('mobile-more-menu controller', () => {
    let application;

    beforeEach(async () => {
        stubDialog();
        application = Application.start();
        application.register('mobile-more-menu', MobileMoreMenuController);
        buildDom();
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('opens the panel and flags the button as expanded', () => {
        moreButton().click();

        expect(dialog().open).toBe(true);
        expect(moreButton().getAttribute('aria-expanded')).toBe('true');
    });

    it('closes the panel from its close button', () => {
        moreButton().click();

        document.querySelector('.close').click();

        expect(dialog().open).toBe(false);
        expect(moreButton().getAttribute('aria-expanded')).toBe('false');
    });

    it('closes the panel on a tap beside it', () => {
        moreButton().click();

        dialog().dispatchEvent(new MouseEvent('click', { bubbles: true }));

        expect(dialog().open).toBe(false);
    });

    it('stays open on a tap inside the panel', () => {
        moreButton().click();

        document.querySelector('.content').click();

        expect(dialog().open).toBe(true);
    });

    it('resets the button once closed by Escape', () => {
        moreButton().click();

        dialog().open = false;
        dialog().dispatchEvent(new Event('close'));

        expect(moreButton().getAttribute('aria-expanded')).toBe('false');
    });

    it('never leaves the panel open in the Turbo cache', () => {
        moreButton().click();

        document.dispatchEvent(new Event('turbo:before-cache'));

        expect(dialog().open).toBe(false);
    });
});
