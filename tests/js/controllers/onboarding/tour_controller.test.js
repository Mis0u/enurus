import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const driveMock = vi.fn();
const destroyMock = vi.fn();
const driverMock = vi.fn(() => ({ drive: driveMock, destroy: destroyMock }));
vi.mock('driver.js', () => ({ driver: driverMock }));

const TourController = (await import('../../../../assets/controllers/onboarding/tour_controller.js')).default;

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

const STEPS = [
    { target: 'welcome', title: 'Bienvenue', description: 'Petit tour' },
    { target: 'new_workout', title: 'Séance', description: 'Ajoute tes exercices' },
    { target: 'help', title: 'Aide', description: 'Tout est expliqué' },
];

const LABELS = { next: 'Suivant', previous: 'Précédent', close: 'Fermer le tour guidé', progress: '{{current}} sur {{total}}' };

// jsdom ne fait aucun rendu : `getClientRects()` y est toujours vide. Un élément « affiché » est
// simulé en lui donnant un rectangle.
function markAsDisplayed(element) {
    element.getClientRects = () => [{ width: 10, height: 10 }];
}

function buildDom() {
    document.body.innerHTML = `
        <a id="sidebar-new-workout" data-tour-step="new_workout"></a>
        <a id="mobile-new-workout" data-tour-step="new_workout" aria-haspopup="dialog" aria-controls="more-menu" aria-expanded="false"></a>
        <div data-controller="onboarding--tour"
             data-onboarding--tour-steps-value='${JSON.stringify(STEPS)}'
             data-onboarding--tour-labels-value='${JSON.stringify(LABELS)}'
             data-onboarding--tour-finish-label-value="Enregistrer ma première séance"
             data-onboarding--tour-finish-url-value="/fr/enregistre-seance"></div>
    `;
    markAsDisplayed(document.getElementById('mobile-new-workout'));
}

const driverConfig = () => driverMock.mock.calls[0][0];

describe('onboarding--tour controller', () => {
    let application;

    beforeEach(async () => {
        driverMock.mockClear();
        driveMock.mockClear();
        destroyMock.mockClear();
        buildDom();
        application = Application.start();
        application.register('onboarding--tour', TourController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('starts the tour as soon as it is displayed', () => {
        expect(driveMock).toHaveBeenCalledOnce();
    });

    it('uses the translated labels', () => {
        expect(driverConfig()).toMatchObject({
            nextBtnText: 'Suivant',
            prevBtnText: 'Précédent',
            progressText: '{{current}} sur {{total}}',
            doneBtnText: 'Enregistrer ma première séance',
        });
    });

    it('points each step at the target actually displayed (desktop sidebar or mobile bar)', () => {
        expect(driverConfig().steps[1].element).toBe(document.getElementById('mobile-new-workout'));
    });

    it('centres the step when no target is displayed', () => {
        expect(driverConfig().steps[0].element).toBeUndefined();
        expect(driverConfig().steps[2].element).toBeUndefined();
    });

    it('replaces the hardcoded English label of the close button', () => {
        const closeButton = document.createElement('button');
        closeButton.setAttribute('aria-label', 'Close');

        driverConfig().onPopoverRender({ closeButton });

        expect(closeButton.getAttribute('aria-label')).toBe('Fermer le tour guidé');
    });

    it('sends the user to the workout form from the last step', () => {
        const assignMock = vi.fn();
        vi.stubGlobal('location', { assign: assignMock });

        driverConfig().steps[2].popover.onNextClick();

        expect(destroyMock).toHaveBeenCalled();
        expect(assignMock).toHaveBeenCalledWith('/fr/enregistre-seance');
        vi.unstubAllGlobals();
    });

    it('keeps the default "next" behaviour on the other steps', () => {
        expect(driverConfig().steps[1].popover.onNextClick).toBeUndefined();
    });

    it('restores the ARIA attributes that driver.js removes from highlighted elements', async () => {
        const target = document.getElementById('mobile-new-workout');
        ['aria-controls', 'aria-expanded', 'aria-haspopup'].forEach((name) => target.removeAttribute(name));
        target.setAttribute('aria-hidden', 'true');

        driverConfig().onDestroyStarted();
        await new Promise((resolve) => requestAnimationFrame(resolve));

        expect(destroyMock).toHaveBeenCalled();
        expect(target.getAttribute('aria-controls')).toBe('more-menu');
        expect(target.getAttribute('aria-expanded')).toBe('false');
        expect(target.getAttribute('aria-haspopup')).toBe('dialog');
    });

    it('closes the tour when the controller leaves the page', async () => {
        document.querySelector('[data-controller="onboarding--tour"]').remove();
        await nextTick();

        expect(destroyMock).toHaveBeenCalled();
    });
});
