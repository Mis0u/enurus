import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import ContactPollOptionsController from '../../../../assets/controllers/admin/contact_poll_options_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function buildDom({ checkedCategory = 'informative', initialOptions = [] } = {}) {
    document.body.innerHTML = `
        <div data-controller="admin--contact-poll-options"
             data-admin--contact-poll-options-placeholder-value="Libellé de l'option"
             data-admin--contact-poll-options-remove-label-value="Retirer"
             data-admin--contact-poll-options-initial-options-value='${JSON.stringify(initialOptions)}'>

            <input type="radio" name="category" value="informative" data-admin--contact-poll-options-target="categoryInput"
                   data-action="change->admin--contact-poll-options#onCategoryChange" ${checkedCategory === 'informative' ? 'checked' : ''}>
            <input type="radio" name="category" value="vote" data-admin--contact-poll-options-target="categoryInput"
                   data-action="change->admin--contact-poll-options#onCategoryChange" ${checkedCategory === 'vote' ? 'checked' : ''}>

            <div data-admin--contact-poll-options-target="pollFields" hidden>
                <div data-admin--contact-poll-options-target="list"></div>
                <button data-action="click->admin--contact-poll-options#addOption"></button>
                <input type="hidden" data-admin--contact-poll-options-target="hiddenOptions">
            </div>
        </div>
    `;
}

describe('admin--contact-poll-options controller', () => {
    let application;

    beforeEach(() => {
        application = Application.start();
        application.register('admin--contact-poll-options', ContactPollOptionsController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('keeps the poll fields hidden when the informative category is selected', async () => {
        buildDom({ checkedCategory: 'informative' });
        await nextTick();

        expect(document.querySelector('[data-admin--contact-poll-options-target="pollFields"]').hidden).toBe(true);
    });

    it('shows the poll fields and seeds two empty option rows when the vote category is selected on connect', async () => {
        buildDom({ checkedCategory: 'vote' });
        await nextTick();

        expect(document.querySelector('[data-admin--contact-poll-options-target="pollFields"]').hidden).toBe(false);
        expect(document.querySelectorAll('[data-poll-option-row]')).toHaveLength(2);
    });

    it('restores pre-existing option labels on connect (edit mode)', async () => {
        buildDom({ checkedCategory: 'vote', initialOptions: ['Oui', 'Non'] });
        await nextTick();

        const inputs = document.querySelectorAll('[data-poll-option-row] input');
        expect([...inputs].map(i => i.value)).toEqual(['Oui', 'Non']);
    });

    it('switching from informative to vote reveals the fields and seeds default rows', async () => {
        buildDom({ checkedCategory: 'informative' });
        await nextTick();

        const voteRadio = document.querySelector('input[value="vote"]');
        voteRadio.checked = true;
        voteRadio.dispatchEvent(new Event('change'));

        expect(document.querySelector('[data-admin--contact-poll-options-target="pollFields"]').hidden).toBe(false);
        expect(document.querySelectorAll('[data-poll-option-row]')).toHaveLength(2);
    });

    it('addOption appends a new empty row', async () => {
        buildDom({ checkedCategory: 'vote' });
        await nextTick();

        document.querySelector('[data-action="click->admin--contact-poll-options#addOption"]').click();

        expect(document.querySelectorAll('[data-poll-option-row]')).toHaveLength(3);
    });

    it('typing in an option input syncs the hidden JSON, trimming and dropping empty values', async () => {
        buildDom({ checkedCategory: 'vote', initialOptions: ['Oui', 'Non'] });
        await nextTick();

        const inputs = document.querySelectorAll('[data-poll-option-row] input');
        inputs[0].value = '  Oui  ';
        inputs[0].dispatchEvent(new Event('input'));
        inputs[1].value = '   ';
        inputs[1].dispatchEvent(new Event('input'));

        const hidden = document.querySelector('[data-admin--contact-poll-options-target="hiddenOptions"]');
        expect(JSON.parse(hidden.value)).toEqual(['Oui']);
    });

    it('removeOption removes the row and re-syncs the hidden input', async () => {
        buildDom({ checkedCategory: 'vote', initialOptions: ['Oui', 'Non'] });
        await nextTick();

        document.querySelectorAll('[data-poll-option-row] button')[0].click();

        expect(document.querySelectorAll('[data-poll-option-row]')).toHaveLength(1);
        const hidden = document.querySelector('[data-admin--contact-poll-options-target="hiddenOptions"]');
        expect(JSON.parse(hidden.value)).toEqual(['Non']);
    });
});
