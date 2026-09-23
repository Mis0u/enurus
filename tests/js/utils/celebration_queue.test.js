import { describe, expect, it } from 'vitest';
import { enqueueCelebration } from '../../../assets/utils/celebration_queue.js';

function deferred() {
    let resolve;
    const promise = new Promise(done => { resolve = done; });

    return { promise, resolve };
}

describe('enqueueCelebration', () => {
    it('opens the next popup only once the previous one is closed', async () => {
        const opened = [];
        const first = deferred();

        enqueueCelebration(() => { opened.push('goal'); return first.promise; });
        const last = enqueueCelebration(() => { opened.push('badge'); });

        await Promise.resolve();
        await Promise.resolve();
        expect(opened).toEqual(['goal']);

        first.resolve();
        await last;
        expect(opened).toEqual(['goal', 'badge']);
    });

    it('keeps going when a popup fails', async () => {
        const opened = [];

        enqueueCelebration(() => { throw new Error('boom'); });
        await enqueueCelebration(() => { opened.push('next'); });

        expect(opened).toEqual(['next']);
    });
});
