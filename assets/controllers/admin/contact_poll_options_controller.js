import { Controller } from '@hotwired/stimulus';

const VOTE_CATEGORY = 'vote';
const INITIAL_OPTION_ROWS = 2;

export default class extends Controller {
    static targets = ['categoryInput', 'pollFields', 'list', 'hiddenOptions'];

    static values = {
        placeholder: String,
        removeLabel: String,
        initialOptions: Array,
    };

    connect() {
        this.initialOptionsValue.forEach(label => this.#appendRow(label));
        this.#applyVisibility();
    }

    onCategoryChange() {
        this.#applyVisibility();
        this.#sync();
    }

    addOption() {
        this.#appendRow('');
        this.#sync();
    }

    removeOption(event) {
        event.currentTarget.closest('[data-poll-option-row]')?.remove();
        this.#sync();
    }

    syncFromInput() {
        this.#sync();
    }

    #applyVisibility() {
        const isVote = this.categoryInputTargets.find(input => input.checked)?.value === VOTE_CATEGORY;

        this.pollFieldsTarget.hidden = !isVote;

        if (isVote && 0 === this.listTarget.children.length) {
            for (let i = 0; i < INITIAL_OPTION_ROWS; i++) {
                this.#appendRow('');
            }
        }
    }

    #appendRow(value) {
        const row = document.createElement('div');
        row.dataset.pollOptionRow = '';
        row.className = 'admin-poll-option-row';

        const input = document.createElement('input');
        input.type = 'text';
        input.value = value;
        input.placeholder = this.placeholderValue;
        input.className = 'form-control';
        input.addEventListener('input', () => this.syncFromInput());

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'btn btn-link text-danger';
        removeBtn.textContent = this.removeLabelValue;
        removeBtn.dataset.action = 'click->admin--contact-poll-options#removeOption';

        row.append(input, removeBtn);
        this.listTarget.append(row);
    }

    #sync() {
        const labels = Array.from(this.listTarget.querySelectorAll('input'))
            .map(input => input.value.trim())
            .filter(value => '' !== value);

        this.hiddenOptionsTarget.value = JSON.stringify(labels);
    }
}
