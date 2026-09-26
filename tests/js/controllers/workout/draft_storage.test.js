import { afterEach, describe, expect, it, vi } from 'vitest';
import { clearDraft, DRAFT_TTL_MS, loadDraft, purgeAllDrafts, saveDraft } from '../../../../assets/controllers/workout/draft_storage.js';

const NOW = 1_800_000_000_000;
const DRAFT = { date: '2026-09-26', duration: '60', routineId: '', exercises: [{ exerciseId: 'ex-1', sets: [] }] };

describe('workout draft storage', () => {
    afterEach(() => {
        localStorage.clear();
        vi.restoreAllMocks();
    });

    it('gives back a draft saved less than an hour ago', () => {
        saveDraft('user-1', DRAFT, NOW);

        expect(loadDraft('user-1', NOW + DRAFT_TTL_MS - 1)).toMatchObject(DRAFT);
    });

    it('forgets a draft untouched for an hour, and removes it', () => {
        saveDraft('user-1', DRAFT, NOW);

        expect(loadDraft('user-1', NOW + DRAFT_TTL_MS)).toBeNull();
        expect(localStorage.length).toBe(0);
    });

    it('keeps each user draft apart', () => {
        saveDraft('user-1', DRAFT, NOW);

        expect(loadDraft('user-2', NOW)).toBeNull();
    });

    it('ignores a corrupted draft', () => {
        localStorage.setItem('enurus:workout-draft:user-1', '{not json');

        expect(loadDraft('user-1', NOW)).toBeNull();
    });

    it('clears the draft of one user', () => {
        saveDraft('user-1', DRAFT, NOW);

        clearDraft('user-1');

        expect(loadDraft('user-1', NOW)).toBeNull();
    });

    it('purges every user draft but nothing else', () => {
        saveDraft('user-1', DRAFT, NOW);
        saveDraft('user-2', DRAFT, NOW);
        localStorage.setItem('other-key', 'kept');

        purgeAllDrafts();

        expect(localStorage.length).toBe(1);
        expect(localStorage.getItem('other-key')).toBe('kept');
    });

    it('never throws when the storage is unavailable', () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new Error('QuotaExceededError');
        });
        vi.spyOn(Storage.prototype, 'getItem').mockImplementation(() => {
            throw new Error('SecurityError');
        });

        expect(() => saveDraft('user-1', DRAFT, NOW)).not.toThrow();
        expect(loadDraft('user-1', NOW)).toBeNull();
    });
});
