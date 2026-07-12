import { Controller } from '@hotwired/stimulus';
import { switchDashboardTab } from '../../utils/dashboard_tabs.js';

export default class extends Controller {
    static targets = ['tab', 'chartPanel'];

    switchFilter(event) {
        const filter = event.currentTarget.dataset.filter;

        switchDashboardTab(this.tabTargets, filter);

        this.chartPanelTargets.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.filter !== filter);
        });

        // Chart.js calcule la taille du canvas au moment où son conteneur redevient visible ;
        // ce resize forcé évite un canvas figé à 0×0 après un switch depuis un panneau caché.
        window.dispatchEvent(new Event('resize'));
    }
}
