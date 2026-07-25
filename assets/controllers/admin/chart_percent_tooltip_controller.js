import { Controller } from '@hotwired/stimulus';

/**
 * Ajoute un suffixe "%" au tooltip d'un graphique Chart.js (symfony/ux-chartjs) dont les valeurs
 * sont déjà des pourcentages — aucune option PHP ne permet de passer une fonction callback (payload
 * JSON), donc on la construit ici, côté client, sur l'événement `chartjs:pre-connect` (déclenché
 * juste avant la construction du chart, cf. @symfony/ux-chartjs/chart controller.js).
 */
export default class extends Controller {
    connect() {
        this.element.addEventListener('chartjs:pre-connect', this.onPreConnect);
    }

    disconnect() {
        this.element.removeEventListener('chartjs:pre-connect', this.onPreConnect);
    }

    onPreConnect = (event) => {
        const { options } = event.detail;

        options.plugins = options.plugins ?? {};
        options.plugins.tooltip = options.plugins.tooltip ?? {};
        options.plugins.tooltip.callbacks = options.plugins.tooltip.callbacks ?? {};
        options.plugins.tooltip.callbacks.label = (context) => {
            const label = context.label ?? context.dataset?.label ?? '';
            const raw = context.parsed;
            const value = raw && 'object' === typeof raw ? (raw.x ?? raw.y) : raw;

            return `${label}: ${value}%`;
        };
    };
}
