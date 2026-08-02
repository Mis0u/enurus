import { describe, expect, it } from 'vitest';
import { normalizeForSearch } from '../../../assets/utils/search.js';

describe('normalizeForSearch', () => {
    it('strips accents and lowercases the value', () => {
        expect(normalizeForSearch('Développé couché')).toBe('developpe couche');
    });

    it('leaves already-normalized text unchanged', () => {
        expect(normalizeForSearch('squat')).toBe('squat');
    });
});
