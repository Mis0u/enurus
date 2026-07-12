/**
 * Bascule l'état actif/inactif d'un groupe d'onglets de filtre du dashboard
 * (widgets Muscles, Tonnage, Séance — tous partagent le même comportement visuel).
 *
 * @param {HTMLElement[]} tabTargets
 * @param {string} filter
 */
export function switchDashboardTab(tabTargets, filter) {
    tabTargets.forEach(tab => {
        const isActive = tab.dataset.filter === filter;
        tab.classList.toggle('dashboard-tab-active', isActive);
        tab.classList.toggle('dashboard-tab-inactive', !isActive);
    });
}
