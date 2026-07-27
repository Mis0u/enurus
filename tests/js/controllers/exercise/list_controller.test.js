import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import ExerciseListController from '../../../../assets/controllers/exercise/list_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function card(id, { name, type }) {
    return `<div data-exercise--list-target="card" data-name="${name}" data-type="${type}" id="${id}"></div>`;
}

async function buildDom(cardsHtml) {
    document.body.innerHTML = `
        <div data-controller="exercise--list">
            <input data-exercise--list-target="searchInput" data-action="input->exercise--list#onSearch">
            <button data-exercise--list-target="filterBtn" data-filter-type="chest"
                    data-action="click->exercise--list#toggleFilter"></button>
            <button data-exercise--list-target="filterBtn" data-filter-type="back"
                    data-action="click->exercise--list#toggleFilter"></button>
            <button data-exercise--list-target="resetBtn" data-action="click->exercise--list#resetFilters"></button>

            <span data-exercise--list-target="counter">
                <span data-exercise--list-target="counterText"></span>
            </span>

            <div data-exercise--list-target="grid">${cardsHtml}</div>
            <div data-exercise--list-target="emptyState" hidden></div>

            <div data-exercise--list-target="paginationWrapper">
                <button data-exercise--list-target="firstBtn" data-action="click->exercise--list#goFirst"></button>
                <button data-exercise--list-target="prevBtn" data-action="click->exercise--list#prevPage"></button>
                <div data-exercise--list-target="pageNumbers"></div>
                <button data-exercise--list-target="nextBtn" data-action="click->exercise--list#nextPage"></button>
                <button data-exercise--list-target="lastBtn" data-action="click->exercise--list#goLast"></button>
                <select data-exercise--list-target="perPageSelect" data-action="change->exercise--list#onPerPageChange">
                    <option value="5">5</option>
                    <option value="12">12</option>
                    <option value="24">24</option>
                </select>
            </div>
        </div>
    `;

    await nextTick();
}

function visibleCardIds() {
    return [...document.querySelectorAll('[data-exercise--list-target="card"]')]
        .filter(el => !el.hidden)
        .map(el => el.id);
}

describe('exercise--list controller', () => {
    let application;

    beforeEach(() => {
        Element.prototype.scrollIntoView = vi.fn();
        application = Application.start();
        application.register('exercise--list', ExerciseListController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('shows every card and the plural count on connect', async () => {
        await buildDom([
            card('c1', { name: 'squat', type: 'legs' }),
            card('c2', { name: 'bench press', type: 'chest' }),
        ].join(''));

        expect(visibleCardIds()).toEqual(['c1', 'c2']);
        expect(document.querySelector('[data-exercise--list-target="counterText"]').textContent).toBe('2 exercices');
    });

    it('filters cards by search text, case-insensitively against the pre-lowercased data-name', async () => {
        await buildDom([
            card('c1', { name: 'squat', type: 'legs' }),
            card('c2', { name: 'bench press', type: 'chest' }),
        ].join(''));

        const input = document.querySelector('[data-exercise--list-target="searchInput"]');
        input.value = 'squ';
        input.dispatchEvent(new Event('input'));

        expect(visibleCardIds()).toEqual(['c1']);
        expect(document.querySelector('[data-exercise--list-target="counterText"]').textContent).toBe('1 exercice');
    });

    it('toggling the same filter twice clears it back to all cards', async () => {
        await buildDom([
            card('c1', { name: 'squat', type: 'legs' }),
            card('c2', { name: 'bench press', type: 'chest' }),
        ].join(''));

        const chestBtn = document.querySelector('[data-filter-type="chest"]');
        chestBtn.click();
        expect(visibleCardIds()).toEqual(['c2']);
        expect(chestBtn.classList.contains('is-active')).toBe(true);

        chestBtn.click();
        expect(visibleCardIds()).toEqual(['c1', 'c2']);
        expect(chestBtn.classList.contains('is-active')).toBe(false);
    });

    it('resetFilters clears both the search input and the active type filter', async () => {
        await buildDom([
            card('c1', { name: 'squat', type: 'legs' }),
            card('c2', { name: 'bench press', type: 'chest' }),
        ].join(''));

        document.querySelector('[data-filter-type="chest"]').click();
        const input = document.querySelector('[data-exercise--list-target="searchInput"]');
        input.value = 'bench';
        input.dispatchEvent(new Event('input'));
        expect(visibleCardIds()).toEqual(['c2']);

        document.querySelector('[data-exercise--list-target="resetBtn"]').click();

        expect(input.value).toBe('');
        expect(visibleCardIds()).toEqual(['c1', 'c2']);
    });

    it('shows the reset button only when a filter or search term is active', async () => {
        await buildDom(card('c1', { name: 'squat', type: 'legs' }));
        const resetBtn = document.querySelector('[data-exercise--list-target="resetBtn"]');

        expect(resetBtn.hidden).toBe(true);

        document.querySelector('[data-filter-type="chest"]').click();
        expect(resetBtn.hidden).toBe(false);
    });

    it('shows the empty state and hides the grid and pagination when nothing matches', async () => {
        await buildDom(card('c1', { name: 'squat', type: 'legs' }));

        const input = document.querySelector('[data-exercise--list-target="searchInput"]');
        input.value = 'nonexistent';
        input.dispatchEvent(new Event('input'));

        expect(document.querySelector('[data-exercise--list-target="grid"]').hidden).toBe(true);
        expect(document.querySelector('[data-exercise--list-target="emptyState"]').hidden).toBe(false);
        expect(document.querySelector('[data-exercise--list-target="paginationWrapper"]').hidden).toBe(true);
    });

    it('paginates cards according to the selected per-page size and disables prev/next at the bounds', async () => {
        const cards = Array.from({ length: 12 }, (_, i) => card(`c${i}`, { name: `ex${i}`, type: 'legs' })).join('');
        await buildDom(cards);

        const select = document.querySelector('[data-exercise--list-target="perPageSelect"]');
        select.value = '5';
        select.dispatchEvent(new Event('change'));

        expect(visibleCardIds()).toEqual(['c0', 'c1', 'c2', 'c3', 'c4']);
        expect(document.querySelector('[data-exercise--list-target="prevBtn"]').disabled).toBe(true);
        expect(document.querySelector('[data-exercise--list-target="nextBtn"]').disabled).toBe(false);

        document.querySelector('[data-exercise--list-target="nextBtn"]').click();
        expect(visibleCardIds()).toEqual(['c5', 'c6', 'c7', 'c8', 'c9']);

        document.querySelector('[data-exercise--list-target="lastBtn"]').click();
        expect(visibleCardIds()).toEqual(['c10', 'c11']);
        expect(document.querySelector('[data-exercise--list-target="nextBtn"]').disabled).toBe(true);

        document.querySelector('[data-exercise--list-target="firstBtn"]').click();
        expect(visibleCardIds()).toEqual(['c0', 'c1', 'c2', 'c3', 'c4']);
    });

    it('renders one page-number button per page within the 5-wide sliding window', async () => {
        const cards = Array.from({ length: 20 }, (_, i) => card(`c${i}`, { name: `ex${i}`, type: 'legs' })).join('');
        await buildDom(cards);

        const select = document.querySelector('[data-exercise--list-target="perPageSelect"]');
        select.value = '5';
        select.dispatchEvent(new Event('change'));

        // 20 cartes / 5 par page = 4 pages, toutes dans la fenêtre de 5 -> aucun besoin de first/last.
        const pageButtons = document.querySelectorAll('[data-exercise--list-target="pageNumbers"] button');
        expect(pageButtons).toHaveLength(4);
        expect([...pageButtons].map(btn => btn.textContent)).toEqual(['1', '2', '3', '4']);
        expect(document.querySelector('[data-exercise--list-target="firstBtn"]').hidden).toBe(true);
        expect(document.querySelector('[data-exercise--list-target="lastBtn"]').hidden).toBe(true);
    });

    it('clicking a page-number button jumps directly to that page', async () => {
        const cards = Array.from({ length: 15 }, (_, i) => card(`c${i}`, { name: `ex${i}`, type: 'legs' })).join('');
        await buildDom(cards);

        const select = document.querySelector('[data-exercise--list-target="perPageSelect"]');
        select.value = '5';
        select.dispatchEvent(new Event('change'));

        const thirdPageBtn = [...document.querySelectorAll('[data-exercise--list-target="pageNumbers"] button')]
            .find(btn => btn.textContent === '3');
        thirdPageBtn.click();

        expect(visibleCardIds()).toEqual(['c10', 'c11', 'c12', 'c13', 'c14']);
    });

    it('changing the per-page size resets back to page 1', async () => {
        const cards = Array.from({ length: 15 }, (_, i) => card(`c${i}`, { name: `ex${i}`, type: 'legs' })).join('');
        await buildDom(cards);

        const select = document.querySelector('[data-exercise--list-target="perPageSelect"]');
        select.value = '5';
        select.dispatchEvent(new Event('change'));
        document.querySelector('[data-exercise--list-target="nextBtn"]').click();
        expect(visibleCardIds()).toEqual(['c5', 'c6', 'c7', 'c8', 'c9']);

        select.value = '12';
        select.dispatchEvent(new Event('change'));

        expect(visibleCardIds()).toEqual(Array.from({ length: 12 }, (_, i) => `c${i}`));
    });
});
