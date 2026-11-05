import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';

const CalculatorController = (await import('../../../../assets/controllers/one_rep_max/calculator_controller.js')).default;

describe('one-rep-max--calculator controller', () => {
    let application;

    const target = (name) => document.querySelector(`[data-one-rep-max--calculator-target="${name}"]`);
    const loadRows = () => [...target('loads').querySelectorAll('tr')];
    const cellOf = (row, cell) => row.querySelector(`[data-cell="${cell}"]`).textContent;

    const typeSet = async (weight, reps) => {
        target('weight').value = weight;
        target('reps').value = reps;
        target('reps').dispatchEvent(new Event('input', { bubbles: true }));
        await Promise.resolve();
    };

    beforeEach(async () => {
        document.documentElement.lang = 'fr';
        document.body.innerHTML = `
            <main data-controller="one-rep-max--calculator" data-one-rep-max--calculator-unit-value="kg">
                <button type="button" aria-pressed="true" data-one-rep-max--calculator-target="unitButton"
                        data-one-rep-max--calculator-unit-param="kg" data-action="one-rep-max--calculator#pickUnit">kg</button>
                <button type="button" aria-pressed="false" data-one-rep-max--calculator-target="unitButton"
                        data-one-rep-max--calculator-unit-param="lbs" data-action="one-rep-max--calculator#pickUnit">lbs</button>
                <input value="80" data-one-rep-max--calculator-target="weight" data-action="input->one-rep-max--calculator#compute">
                <span data-one-rep-max--calculator-target="unitLabel">kg</span>
                <input value="6" data-one-rep-max--calculator-target="reps" data-action="input->one-rep-max--calculator#compute">
                <div data-one-rep-max--calculator-target="result" hidden>
                    <span data-one-rep-max--calculator-target="oneRepMax"></span>
                </div>
                <p data-one-rep-max--calculator-target="invalid" hidden></p>
                <div data-one-rep-max--calculator-target="warning" hidden></div>
                <table><tbody data-one-rep-max--calculator-target="loads"></tbody></table>
                <template data-one-rep-max--calculator-target="loadRow">
                    <tr><td data-cell="percent"></td><td data-cell="load"></td><td data-cell="reps"></td></tr>
                </template>
            </main>
        `;

        application = Application.start();
        application.register('one-rep-max--calculator', CalculatorController);
        await vi.waitFor(() => expect(target('result').hidden).toBe(false));
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('estimates the example set as soon as the page opens', () => {
        expect(target('oneRepMax').textContent).toBe('96');
        expect(loadRows()).toHaveLength(9);
        expect(cellOf(loadRows()[2], 'load')).toBe('87,5 kg');
        expect(cellOf(loadRows()[2], 'reps')).toBe('≈ 3');
    });

    it('caps the reps field at the reps the formula accepts', () => {
        expect(target('reps').max).toBe('12');
    });

    it('updates the estimate while the set is typed', async () => {
        await typeSet('100', '10');

        expect(target('oneRepMax').textContent).toBe('133,3');
    });

    it('warns that long sets give a rough estimate', async () => {
        await typeSet('60', '11');

        expect(target('warning').hidden).toBe(false);
    });

    it('asks for a valid set instead of guessing', async () => {
        await typeSet('80', '15');

        expect(target('result').hidden).toBe(true);
        expect(target('invalid').hidden).toBe(false);
        expect(target('warning').hidden).toBe(true);
        expect(cellOf(loadRows()[0], 'load')).toBe('—');
    });

    it('switches to pounds and rounds loads to 5 lbs', async () => {
        document.querySelector('[data-one-rep-max--calculator-unit-param="lbs"]').click();
        await vi.waitFor(() => expect(target('unitLabel').textContent).toBe('lbs'));

        expect(document.querySelector('[data-one-rep-max--calculator-unit-param="lbs"]').getAttribute('aria-pressed')).toBe('true');
        expect(document.querySelector('[data-one-rep-max--calculator-unit-param="kg"]').getAttribute('aria-pressed')).toBe('false');
        expect(cellOf(loadRows()[1], 'load')).toBe('90 lbs');
    });
});
