import { describe, expect, it } from 'vitest';
import { editClasses } from '../../../../assets/controllers/passwordRule/_editClasses.js';

function buildTarget() {
    const div = document.createElement('div');
    div.classList.add('text-slate-400');
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.classList.add('text-slate-500');
    div.appendChild(svg);
    return div;
}

describe('editClasses', () => {
    it('marks the rule as valid', () => {
        const target = buildTarget();

        editClasses(true, target);

        expect(target.classList.contains('text-green-400')).toBe(true);
        expect(target.classList.contains('text-slate-400')).toBe(false);
        expect(target.querySelector('svg').classList.contains('text-green-500')).toBe(true);
        expect(target.querySelector('svg').classList.contains('text-slate-500')).toBe(false);
    });

    it('marks the rule as invalid', () => {
        const target = buildTarget();
        editClasses(true, target);

        editClasses(false, target);

        expect(target.classList.contains('text-green-400')).toBe(false);
        expect(target.classList.contains('text-slate-400')).toBe(true);
        expect(target.querySelector('svg').classList.contains('text-green-500')).toBe(false);
        expect(target.querySelector('svg').classList.contains('text-slate-500')).toBe(true);
    });
});
