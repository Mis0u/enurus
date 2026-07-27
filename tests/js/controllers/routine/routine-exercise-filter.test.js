import { describe, expect, it } from 'vitest';
import { normalizeForSearch, matchesFilters } from '../../../../assets/controllers/routine/routine-exercise-filter.js';

describe('normalizeForSearch', () => {
    it('strips accents and lowercases the value', () => {
        expect(normalizeForSearch('Développé couché')).toBe('developpe couche');
    });

    it('leaves already-normalized text unchanged', () => {
        expect(normalizeForSearch('squat')).toBe('squat');
    });
});

describe('matchesFilters', () => {
    const data = {
        normalizedName: 'developpe couche',
        primaryMuscleGroupIds: ['chest'],
        secondaryMuscleGroupIds: ['triceps'],
    };

    it('matches everything when there is no search query and no muscle filter', () => {
        expect(matchesFilters(data, '', [])).toBe(true);
    });

    it('matches on a partial, already-normalized search query', () => {
        expect(matchesFilters(data, 'developpe', [])).toBe(true);
        expect(matchesFilters(data, 'squat', [])).toBe(false);
    });

    it('matches when a primary muscle filter targets a muscle assigned as primary', () => {
        expect(matchesFilters(data, '', [{ id: 'chest', type: 'primary' }])).toBe(true);
    });

    it('does not match when the muscle id is assigned but with the wrong type', () => {
        expect(matchesFilters(data, '', [{ id: 'chest', type: 'secondary' }])).toBe(false);
    });

    it('treats multiple muscle filters as OR, matching if any one is satisfied', () => {
        const filters = [
            { id: 'quadriceps', type: 'primary' },
            { id: 'triceps', type: 'secondary' },
        ];

        expect(matchesFilters(data, '', filters)).toBe(true);
    });

    it('requires both the search query and the muscle filter to match', () => {
        expect(matchesFilters(data, 'squat', [{ id: 'chest', type: 'primary' }])).toBe(false);
    });
});
