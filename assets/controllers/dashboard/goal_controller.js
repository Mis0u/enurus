import { Controller } from '@hotwired/stimulus';
import { switchDashboardTab } from '../../utils/dashboard_tabs.js';

export default class extends Controller {
    static targets = ['tab', 'panel'];

    switchFilter(event) {
        const filter = event.currentTarget.dataset.filter;

        switchDashboardTab(this.tabTargets, filter);

        this.panelTargets.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.filter !== filter);
            panel.classList.toggle('flex', panel.dataset.filter === filter);
        });
    }
}
