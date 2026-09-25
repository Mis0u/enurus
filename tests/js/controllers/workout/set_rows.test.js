import { afterEach, describe, expect, it } from 'vitest';
import { insertSetRow, resetPrefilledCard } from '../../../../assets/controllers/workout/set_rows.js';

function buildPrefilledCard() {
    document.body.innerHTML = `
        <div data-exercise-index="2">
            <div class="js-prefilled-from">Repris de ta séance du 12 sept. 2026</div>
            <table><tbody class="js-sets-tbody">
                <tr data-set-index="0"><td>1</td><td><input name="w[2][exerciseSets][0][weight]" value="80"></td></tr>
                <tr data-set-index="1"><td>2</td><td><input name="w[2][exerciseSets][1][weight]" value="85"></td></tr>
            </tbody></table>
            <template class="js-set-template">
                <tr data-set-index="__SET_INDEX__"><td>__SET_NUMBER__</td><td><input name="w[__EXERCISE_INDEX__][exerciseSets][__SET_INDEX__][weight]"></td></tr>
            </template>
        </div>
    `;

    return document.querySelector('[data-exercise-index]');
}

describe('set rows', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('appends a set row numbered after the existing ones', () => {
        const card = buildPrefilledCard();

        const row = insertSetRow(card, card.querySelector('.js-sets-tbody'));

        expect(row.dataset.setIndex).toBe('2');
        expect(row.querySelector('td').textContent).toBe('3');
        expect(row.querySelector('input').name).toBe('w[2][exerciseSets][2][weight]');
    });

    it('resets a prefilled card to a single empty set and drops the prefill notice', () => {
        const card = buildPrefilledCard();

        resetPrefilledCard(card);

        const rows = card.querySelectorAll('.js-sets-tbody tr');
        expect(rows).toHaveLength(1);
        expect(rows[0].querySelector('input').name).toBe('w[2][exerciseSets][0][weight]');
        expect(rows[0].querySelector('input').value).toBe('');
        expect(card.querySelector('.js-prefilled-from')).toBeNull();
    });
});
