import { Controller } from '@hotwired/stimulus';

const DEBOUNCE_MS = 250;

export default class extends Controller {
    static targets = ['query', 'hidden', 'results', 'localeDisplay'];
    static values = {
        searchUrl: String,
        localeLabel: String,
    };

    connect() {
        this.debounceTimeout = null;
    }

    search() {
        this.hiddenTarget.value = '';
        this.localeDisplayTarget.textContent = '';

        window.clearTimeout(this.debounceTimeout);

        const query = this.queryTarget.value.trim();

        if ('' === query) {
            this.#renderResults([]);

            return;
        }

        this.debounceTimeout = window.setTimeout(() => this.#fetchResults(query), DEBOUNCE_MS);
    }

    select(event) {
        const { id, email, locale } = event.currentTarget.dataset;

        this.hiddenTarget.value = id;
        this.queryTarget.value = email;
        this.localeDisplayTarget.textContent = `${this.localeLabelValue} ${locale}`;

        this.#renderResults([]);
    }

    async #fetchResults(query) {
        try {
            const response = await fetch(`${this.searchUrlValue}?query=${encodeURIComponent(query)}`);

            if (!response.ok) {
                return;
            }

            this.#renderResults(await response.json());
        } catch {
            this.#renderResults([]);
        }
    }

    #renderResults(users) {
        this.resultsTarget.innerHTML = '';
        this.resultsTarget.hidden = 0 === users.length;

        for (const user of users) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = user.email;
            button.dataset.id = user.id;
            button.dataset.email = user.email;
            button.dataset.locale = user.locale;
            button.dataset.action = 'click->admin--recipient-autocomplete#select';

            this.resultsTarget.append(button);
        }
    }
}
