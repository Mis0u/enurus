import { Controller } from '@hotwired/stimulus';

const STYLE_OPTIONS = {
    date: { day: 'numeric', month: 'long', year: 'numeric' },
    datetime: {
        day: 'numeric', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false,
    },
    'datetime-numeric': {
        day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: false,
    },
};

export default class extends Controller {
    static values = {
        iso: String,
        locale: { type: String, default: 'fr' },
        style: { type: String, default: 'datetime' },
    };

    connect() {
        const date = new Date(this.isoValue);

        if (Number.isNaN(date.getTime())) return;

        const options = STYLE_OPTIONS[this.styleValue] ?? STYLE_OPTIONS.datetime;

        this.element.textContent = new Intl.DateTimeFormat(this.localeValue, options).format(date);
    }
}
