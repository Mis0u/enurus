import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn();
vi.mock('sweetalert2', () => ({ default: { fire: fireMock } }));

const ExerciseCreateController = (await import('../../../../assets/controllers/exercise/create_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function pill(muscleId, label) {
    return `
        <button data-exercise--create-target="pill" data-muscle-id="${muscleId}" data-muscle-label="${label}"
                data-svg-ids="[]" data-action="click->exercise--create#cyclePill"></button>
    `;
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="exercise--create"
             data-exercise--create-label-none-value="Aucun"
             data-exercise--create-check-duplicate-url-value="/exercice/verifie-doublon"
             data-exercise--create-duplicate-custom-message-value="%name% existe déjà (créé le %date%)"
             data-exercise--create-duplicate-public-message-value="%name% existe déjà dans le référentiel public">

            <input data-exercise--create-target="nameInput"
                   data-action="input->exercise--create#clearNameError blur->exercise--create#checkDuplicate">
            <div data-exercise--create-target="nameError" hidden></div>

            ${pill('chest', 'Chest')}
            ${pill('back', 'Back')}

            <div data-exercise--create-target="recapPrimary"></div>
            <div data-exercise--create-target="recapSecondary"></div>
            <input type="hidden" data-exercise--create-target="musclesInput">
            <div data-exercise--create-target="musclesError" hidden></div>

            <button data-action="click->exercise--create#toggleAccordion"></button>
            <div data-exercise--create-target="accordion"></div>
            <div data-exercise--create-target="accordionBody"></div>

            <button data-action="click->exercise--create#submitForm"></button>
        </div>
    `;
}

describe('exercise--create controller', () => {
    let application;

    beforeEach(async () => {
        vi.clearAllMocks();
        Element.prototype.scrollIntoView = vi.fn();
        buildDom();
        application = Application.start();
        application.register('exercise--create', ExerciseCreateController);
        await nextTick();
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    function chestPill() {
        return document.querySelector('[data-muscle-id="chest"]');
    }

    it('cycling a pill updates the recap and the hidden JSON input', () => {
        chestPill().click();

        expect(document.querySelector('[data-exercise--create-target="recapPrimary"]').textContent).toBe('Chest');
        expect(JSON.parse(document.querySelector('[data-exercise--create-target="musclesInput"]').value))
            .toEqual([{ id: 'chest', type: 'primary' }]);
    });

    it('cycling a pill twice moves the muscle from the primary to the secondary recap', () => {
        chestPill().click();
        chestPill().click();

        expect(document.querySelector('[data-exercise--create-target="recapPrimary"]').textContent).toContain('Aucun');
        expect(document.querySelector('[data-exercise--create-target="recapSecondary"]').textContent).toBe('Chest');
    });

    it('submitForm blocks submission and shows the name error when the name is too short', () => {
        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'A';
        chestPill().click();

        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="exercise--create"]'),
            'exercise--create',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(true);
        expect(document.querySelector('[data-exercise--create-target="nameError"]').hidden).toBe(false);
    });

    it('submitForm blocks submission and shows the muscles error when no primary muscle is set', () => {
        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'Squat';

        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="exercise--create"]'),
            'exercise--create',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(true);
        expect(document.querySelector('[data-exercise--create-target="musclesError"]').hidden).toBe(false);
    });

    it('submitForm allows submission with a valid name and a primary muscle', () => {
        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'Squat';
        chestPill().click();

        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="exercise--create"]'),
            'exercise--create',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(false);
    });

    it('checkDuplicate does nothing for a name shorter than 2 characters', async () => {
        vi.stubGlobal('fetch', vi.fn());
        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'A';
        document.querySelector('[data-exercise--create-target="nameInput"]').dispatchEvent(new Event('blur'));
        await nextTick();

        expect(global.fetch).not.toHaveBeenCalled();
    });

    it('checkDuplicate shows a custom-exercise duplicate alert with placeholders filled in', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ type: 'custom', name: 'Squat', date: '2026-01-01' }),
        }));

        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'Squat';
        document.querySelector('[data-exercise--create-target="nameInput"]').dispatchEvent(new Event('blur'));
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenCalledWith(expect.objectContaining({
            text: 'Squat existe déjà (créé le 2026-01-01)',
        }));
    });

    it('checkDuplicate shows a public-exercise duplicate alert', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ type: 'public', name: 'Squat' }),
        }));

        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'Squat';
        document.querySelector('[data-exercise--create-target="nameInput"]').dispatchEvent(new Event('blur'));
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenCalledWith(expect.objectContaining({
            text: 'Squat existe déjà dans le référentiel public',
        }));
    });

    it('checkDuplicate does not alert when no duplicate is reported', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ type: null }),
        }));

        document.querySelector('[data-exercise--create-target="nameInput"]').value = 'Squat';
        document.querySelector('[data-exercise--create-target="nameInput"]').dispatchEvent(new Event('blur'));
        await nextTick();
        await nextTick();

        expect(fireMock).not.toHaveBeenCalled();
    });

    it('clearNameError hides the name error', () => {
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="exercise--create"]'),
            'exercise--create',
        );
        document.querySelector('[data-exercise--create-target="nameError"]').hidden = false;

        controller.clearNameError();

        expect(document.querySelector('[data-exercise--create-target="nameError"]').hidden).toBe(true);
    });
});
