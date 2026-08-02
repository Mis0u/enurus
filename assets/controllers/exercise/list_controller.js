import { Controller } from '@hotwired/stimulus';
import { normalizeForSearch } from '../../utils/search.js';

/**
 * Controller Stimulus pour la bibliothèque d'exercices.
 * Nom généré : exercise--list
 *
 * Responsabilités (SRP) :
 * - Filtrage client-side (type + recherche textuelle)
 * - Mise à jour du compteur
 * - Pagination client-side
 * - Visibilité du bouton reset et de l'état vide
 */
export default class extends Controller {
    static targets = [
        'card',
        'searchInput',
        'filterBtn',
        'resetBtn',
        'counter',
        'counterText',
        'grid',
        'emptyState',
        'paginationWrapper',
        'firstBtn',
        'prevBtn',
        'nextBtn',
        'lastBtn',
        'pageNumbers',
        'perPageSelect',
    ];

    static values = {
        totalCount: Number,
    };

    /** @type {string|null} */
    #activeFilter = null;

    /** @type {number} */
    #currentPage = 1;

    /** @type {number} */
    #perPage = 12;

    /** Fenêtre glissante de pages, alignée sur `knp_paginator.page_range` (config/packages/knp_paginator.yaml). */
    #pageRange = 5;

    /** Noms normalisés (accents/casse) des cards, calculés une fois pour éviter de renormaliser à chaque recherche. */
    #normalizedNames = new WeakMap();

    connect() {
        this.cardTargets.forEach(card => {
            this.#normalizedNames.set(card, normalizeForSearch(card.dataset.name));
        });

        this.#applyFilters();
    }

    // =========================================================
    // Actions publiques (data-action)
    // =========================================================

    onSearch() {
        this.#currentPage = 1;
        this.#applyFilters();
    }

    toggleFilter(event) {
        const type = event.currentTarget.dataset.filterType;

        this.#activeFilter = this.#activeFilter === type ? null : type;
        this.#currentPage  = 1;

        this.#updateFilterBtns();
        this.#applyFilters();
    }

    resetFilters() {
        this.searchInputTarget.value = '';
        this.#activeFilter           = null;
        this.#currentPage            = 1;

        this.#updateFilterBtns();
        this.#applyFilters();
    }

    goFirst() {
        if (this.#currentPage <= 1) return;
        this.#currentPage = 1;
        this.#applyFilters();
        this.#scrollToGrid();
    }

    prevPage() {
        if (this.#currentPage <= 1) return;
        this.#currentPage--;
        this.#applyFilters();
        this.#scrollToGrid();
    }

    nextPage() {
        const totalPages = this.#computeTotalPages(this.#getMatchingCards().length);
        if (this.#currentPage >= totalPages) return;
        this.#currentPage++;
        this.#applyFilters();
        this.#scrollToGrid();
    }

    goLast() {
        const totalPages = this.#computeTotalPages(this.#getMatchingCards().length);
        if (this.#currentPage >= totalPages) return;
        this.#currentPage = totalPages;
        this.#applyFilters();
        this.#scrollToGrid();
    }

    onPerPageChange() {
        this.#perPage     = parseInt(this.perPageSelectTarget.value, 10);
        this.#currentPage = 1;
        this.#applyFilters();
    }

    // =========================================================
    // Logique interne
    // =========================================================

    /**
     * Point d'entrée unique. Ordre : filtrer → paginer → mettre à jour UI.
     */
    #applyFilters() {
        const matching = this.#getMatchingCards();

        this.#paginateCards(matching);
        this.#updateCounter(matching.length);
        this.#updateResetBtn();
        this.#updateEmptyState(matching.length);
        this.#renderPagination(matching.length);
    }

    /**
     * Retourne toutes les cards correspondant aux filtres actifs (search + type).
     * @returns {HTMLElement[]}
     */
    #getMatchingCards() {
        const search = normalizeForSearch(this.searchInputTarget.value.trim());

        return this.cardTargets.filter(card => {
            if (this.#activeFilter && card.dataset.type !== this.#activeFilter) {
                return false;
            }
            if (search && !this.#normalizedNames.get(card).includes(search)) {
                return false;
            }
            return true;
        });
    }

    /**
     * Affiche uniquement les cards de la page courante, masque les autres.
     * Utilise aria-hidden plutôt que display:none pour préserver le flux du DOM
     * et éviter les problèmes de hauteur dans le layout scrollable.
     * @param {HTMLElement[]} matching
     */
    #paginateCards(matching) {
        const start = (this.#currentPage - 1) * this.#perPage;
        const end   = start + this.#perPage;
        const pageCards = new Set(matching.slice(start, end));

        this.cardTargets.forEach(card => {
            const visible = pageCards.has(card);
            card.hidden = !visible;
        });
    }

    /**
     * Met à jour le texte du compteur.
     * @param {number} count
     */
    #updateCounter(count) {
        this.counterTextTarget.textContent = count <= 1
            ? `${count} exercice`
            : `${count} exercices`;
    }

    /**
     * Affiche/masque le bouton reset selon l'état actif des filtres.
     * Gestion via hidden uniquement — pas de classes display Tailwind conflictuelles.
     */
    #updateResetBtn() {
        const hasFilter = this.#activeFilter !== null
            || this.searchInputTarget.value.trim() !== '';

        this.resetBtnTarget.hidden = !hasFilter;
    }

    /**
     * Affiche/masque la grille et l'état vide.
     * @param {number} count
     */
    #updateEmptyState(count) {
        const isEmpty = count === 0;

        this.gridTarget.hidden            = isEmpty;
        this.emptyStateTarget.hidden      = !isEmpty;
        this.paginationWrapperTarget.hidden = isEmpty;
    }

    /**
     * Synchronise l'état visuel des boutons de filtre avec #activeFilter.
     */
    #updateFilterBtns() {
        this.filterBtnTargets.forEach(btn => {
            const isActive = btn.dataset.filterType === this.#activeFilter;
            btn.classList.toggle('is-active', isActive);
            btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    /**
     * Génère les boutons de numéros de page et met à jour prev/next.
     * @param {number} totalVisible
     */
    #renderPagination(totalVisible) {
        const totalPages = this.#computeTotalPages(totalVisible);
        const pagesInRange = this.#computePagesInRange(this.#currentPage, totalPages);

        this.pageNumbersTarget.innerHTML = '';

        pagesInRange.forEach(page => {
            this.pageNumbersTarget.appendChild(this.#createPageBtn(page));
        });

        this.prevBtnTarget.disabled = this.#currentPage <= 1;
        this.nextBtnTarget.disabled = this.#currentPage >= totalPages;

        // "Première/dernière page" masqués quand la fenêtre glissante couvre déjà cette borne.
        this.firstBtnTarget.hidden = pagesInRange[0] === 1;
        this.lastBtnTarget.hidden  = pagesInRange[pagesInRange.length - 1] === totalPages;
    }

    /**
     * Fenêtre glissante de pages autour de la page courante — reproduit l'algorithme de
     * Knp\Component\Pager\Pagination\SlidingPagination::getPaginationData() utilisé côté serveur
     * (Mes séances, Mes routines, Messagerie), pour un comportement identique partout.
     * @param {number} current
     * @param {number} pageCount
     * @returns {number[]}
     */
    #computePagesInRange(current, pageCount) {
        let range = Math.min(this.#pageRange, pageCount);
        let delta = Math.ceil(range / 2);

        let start;
        if (current - delta > pageCount - range) {
            start = pageCount - range + 1;
        } else {
            if (current - delta < 0) delta = current;
            start = current - delta + 1;
        }

        return Array.from({length: range}, (_, i) => start + i);
    }

    /**
     * Crée un bouton de numéro de page.
     * @param {number} page
     * @returns {HTMLButtonElement}
     */
    #createPageBtn(page) {
        const btn = document.createElement('button');
        btn.type        = 'button';
        btn.textContent = String(page);
        btn.className   = `exercise-page-btn${page === this.#currentPage ? ' is-active' : ''}`;
        btn.setAttribute('aria-label', `Page ${page}`);
        btn.setAttribute('aria-current', page === this.#currentPage ? 'page' : 'false');

        btn.addEventListener('click', () => {
            this.#currentPage = page;
            this.#applyFilters();
            this.#scrollToGrid();
        });

        return btn;
    }

    /**
     * Scrolle en haut de la grille lors d'un changement de page.
     */
    #scrollToGrid() {
        this.gridTarget.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    /**
     * @param {number} count
     * @returns {number}
     */
    #computeTotalPages(count) {
        return Math.max(1, Math.ceil(count / this.#perPage));
    }
}
