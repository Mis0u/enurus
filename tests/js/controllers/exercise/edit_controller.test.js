import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const fireMock = vi.fn();
vi.mock('sweetalert2', () => ({ default: { fire: fireMock } }));

const ExerciseEditController = (await import('../../../../assets/controllers/exercise/edit_controller.js')).default;

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function pill(muscleId, label) {
    return `
        <button data-exercise--edit-target="pill" data-muscle-id="${muscleId}" data-muscle-label="${label}"
                data-svg-ids="[]" data-action="click->exercise--edit#cyclePill"></button>
    `;
}

function buildDom({ musclesInputValue = '', originalName = '' } = {}) {
    document.body.innerHTML = `
        <div data-controller="exercise--edit"
             data-exercise--edit-label-none-value="Aucun"
             data-exercise--edit-check-duplicate-url-value="/exercice/verifie-doublon"
             data-exercise--edit-duplicate-custom-message-value="%name% existe déjà (créé le %date%)"
             data-exercise--edit-duplicate-public-message-value="%name% existe déjà dans le référentiel public">

            <input data-exercise--edit-target="nameInput" data-original-name="${originalName}"
                   data-action="input->exercise--edit#clearNameError blur->exercise--edit#checkDuplicate">
            <div data-exercise--edit-target="nameError" hidden></div>

            ${pill('chest', 'Chest')}
            ${pill('back', 'Back')}

            <div data-exercise--edit-target="recapPrimary"></div>
            <div data-exercise--edit-target="recapSecondary"></div>
            <input type="hidden" data-exercise--edit-target="musclesInput" value='${musclesInputValue}'>
            <div data-exercise--edit-target="musclesError" hidden></div>

            <div id="description-accordion">
                <button aria-expanded="false"></button>
                <div id="description-body"><textarea></textarea></div>
            </div>

            <button data-action="click->exercise--edit#submitForm"></button>
        </div>
    `;
}

describe('exercise--edit controller', () => {
    let application;

    beforeEach(() => {
        vi.clearAllMocks();
        Element.prototype.scrollIntoView = vi.fn();
        application = Application.start();
        application.register('exercise--edit', ExerciseEditController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.unstubAllGlobals();
    });

    it('restores pill states and the recap from the pre-filled hidden input on connect', async () => {
        buildDom({ musclesInputValue: '[{&quot;id&quot;:&quot;chest&quot;,&quot;type&quot;:&quot;PRIMARY&quot;},{&quot;id&quot;:&quot;back&quot;,&quot;type&quot;:&quot;SECONDARY&quot;}]' });
        await nextTick();

        expect(document.querySelector('[data-exercise--edit-target="recapPrimary"]').textContent).toBe('Chest');
        expect(document.querySelector('[data-exercise--edit-target="recapSecondary"]').textContent).toBe('Back');
    });

    it('ignores a malformed pre-filled hidden input without throwing', async () => {
        buildDom({ musclesInputValue: 'not-json' });
        await nextTick();

        expect(document.querySelector('[data-exercise--edit-target="recapPrimary"]').textContent).toContain('Aucun');
    });

    it('cycling a pill updates the recap and re-serializes the hidden input', async () => {
        buildDom();
        await nextTick();

        document.querySelector('[data-muscle-id="chest"]').click();

        expect(JSON.parse(document.querySelector('[data-exercise--edit-target="musclesInput"]').value))
            .toEqual([{ id: 'chest', type: 'primary' }]);
    });

    it('opens the description accordion on connect when a description is already present', async () => {
        buildDom();
        document.querySelector('#description-body textarea').value = 'Une description existante';
        await nextTick();

        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="exercise--edit"]'),
            'exercise--edit',
        );
        controller.connect();

        expect(document.querySelector('#description-accordion button').getAttribute('aria-expanded')).toBe('true');
        expect(document.querySelector('#description-accordion').classList.contains('open')).toBe(true);
    });

    it('submitForm blocks submission when the name is too short', async () => {
        buildDom();
        await nextTick();
        document.querySelector('[data-exercise--edit-target="nameInput"]').value = 'A';
        document.querySelector('[data-muscle-id="chest"]').click();

        const event = new Event('submit', { cancelable: true });
        const controller = application.getControllerForElementAndIdentifier(
            document.querySelector('[data-controller="exercise--edit"]'),
            'exercise--edit',
        );
        controller.submitForm(event);

        expect(event.defaultPrevented).toBe(true);
    });

    it('checkDuplicate skips the alert when the reported duplicate is the exercise being edited', async () => {
        buildDom({ originalName: 'squat' });
        await nextTick();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ type: 'custom', name: 'Squat', date: '2026-01-01' }),
        }));

        document.querySelector('[data-exercise--edit-target="nameInput"]').value = 'Squat';
        document.querySelector('[data-exercise--edit-target="nameInput"]').dispatchEvent(new Event('blur'));
        await nextTick();
        await nextTick();

        expect(fireMock).not.toHaveBeenCalled();
    });

    it('checkDuplicate alerts when the reported duplicate is a different exercise', async () => {
        buildDom({ originalName: 'bench press' });
        await nextTick();
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ type: 'custom', name: 'Squat', date: '2026-01-01' }),
        }));

        document.querySelector('[data-exercise--edit-target="nameInput"]').value = 'Squat';
        document.querySelector('[data-exercise--edit-target="nameInput"]').dispatchEvent(new Event('blur'));
        await nextTick();
        await nextTick();

        expect(fireMock).toHaveBeenCalledWith(expect.objectContaining({
            text: 'Squat existe déjà (créé le 2026-01-01)',
        }));
    });

    it('checkDuplicate aborts a stale in-flight request when triggered again', async () => {
        buildDom();
        await nextTick();

        const abortSpy = vi.spyOn(AbortController.prototype, 'abort');
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({
            ok: true,
            json: async () => ({ type: null }),
        }));

        const input = document.querySelector('[data-exercise--edit-target="nameInput"]');
        input.value = 'Squat';
        input.dispatchEvent(new Event('blur'));
        input.value = 'Squats';
        input.dispatchEvent(new Event('blur'));
        await nextTick();
        await nextTick();

        expect(abortSpy).toHaveBeenCalled();
        abortSpy.mockRestore();
    });
});
