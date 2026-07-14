import { Controller } from '@hotwired/stimulus';
import { switchDashboardTab } from '../../utils/dashboard_tabs.js';

const CHART_VIEW_ATTR = 'data-symfony--ux-chartjs--chart-view-value';

/**
 * Filtres avec un conteneur scrollable (barres journalières/hebdomadaires potentiellement plus
 * larges que le widget) — "month" ne dépasse jamais 12 barres, jamais de scroll nécessaire.
 */
const SCROLLABLE_FILTERS = ['sessions', 'week'];

export default class extends Controller {
    static targets = ['tab', 'chartPanel'];

    connect() {
        this.#scrollToLastActiveBar('sessions');
    }

    switchFilter(event) {
        const filter = event.currentTarget.dataset.filter;

        switchDashboardTab(this.tabTargets, filter);

        this.chartPanelTargets.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.filter !== filter);
        });

        this.#scrollToLastActiveBar(filter);

        // Chart.js calcule la taille du canvas au moment où son conteneur redevient visible ;
        // ce resize forcé évite un canvas figé à 0×0 après un switch depuis un panneau caché.
        window.dispatchEvent(new Event('resize'));
    }

    /**
     * Positionne le scroll horizontal sur la dernière barre non nulle (dernière séance / dernière
     * semaine travaillée), pour éviter à l'utilisateur de devoir scroller manuellement jusqu'à
     * l'activité la plus récente à chaque ouverture du widget.
     *
     * @param {string} filter
     */
    #scrollToLastActiveBar(filter) {
        if (!SCROLLABLE_FILTERS.includes(filter)) return;

        const panel = this.chartPanelTargets.find(p => p.dataset.filter === filter);
        const canvas = panel?.querySelector('canvas');
        const raw = canvas?.getAttribute(CHART_VIEW_ATTR);

        if (!panel || !canvas || !raw) return;

        let view;
        try {
            view = JSON.parse(raw);
        } catch {
            return;
        }

        const values = view?.data?.datasets?.[0]?.data ?? [];
        const total = values.length;

        if (total === 0) return;

        let lastActiveIndex = -1;
        for (let i = total - 1; i >= 0; i--) {
            if (values[i] > 0) {
                lastActiveIndex = i;
                break;
            }
        }

        // Aucune barre non nulle sur la période affichée : rien à faire, on laisse le scroll par défaut.
        if (lastActiveIndex === -1) return;

        const barWidth = canvas.parentElement.offsetWidth / total;
        const targetRight = (lastActiveIndex + 1) * barWidth;

        panel.scrollLeft = Math.max(0, targetRight - panel.clientWidth);
    }
}
