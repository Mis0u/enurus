import { describe, expect, it, vi } from 'vitest';
import { StoryTimer } from '../../../../assets/controllers/year_in_review/story_timer.js';

// Horloge et planificateur simulés : chaque `tick(ms)` avance le temps puis rejoue la frame en attente.
function fakeFrames() {
    let time = 0;
    let pending = null;

    return {
        now: () => time,
        schedule: (callback) => { pending = callback; return 1; },
        cancel: () => { pending = null; },
        tick(ms) {
            time += ms;
            const callback = pending;
            pending = null;
            callback?.();
        },
    };
}

function timerWith(frames, overrides = {}) {
    const onProgress = vi.fn();
    const onEnd = vi.fn();
    const timer = new StoryTimer({ duration: 1000, onProgress, onEnd, ...frames, ...overrides });

    return { timer, onProgress, onEnd };
}

describe('StoryTimer', () => {
    it('reports progress then ends once the duration is over', () => {
        const frames = fakeFrames();
        const { timer, onProgress, onEnd } = timerWith(frames);

        timer.start();
        frames.tick(250);
        expect(onProgress).toHaveBeenLastCalledWith(0.25);
        frames.tick(750);

        expect(onProgress).toHaveBeenLastCalledWith(1);
        expect(onEnd).toHaveBeenCalledOnce();
    });

    it('does not count the time spent paused', () => {
        const frames = fakeFrames();
        const { timer, onProgress, onEnd } = timerWith(frames);

        timer.start();
        frames.tick(400);
        timer.pause();
        frames.tick(5000);
        timer.resume();
        frames.tick(100);

        expect(onProgress).toHaveBeenLastCalledWith(0.5);
        expect(onEnd).not.toHaveBeenCalled();
    });

    it('restarts from zero when started again', () => {
        const frames = fakeFrames();
        const { timer, onProgress } = timerWith(frames);

        timer.start();
        frames.tick(800);
        timer.start();
        frames.tick(100);

        expect(onProgress).toHaveBeenLastCalledWith(0.1);
    });

    it('stays silent once stopped', () => {
        const frames = fakeFrames();
        const { timer, onEnd } = timerWith(frames);

        timer.start();
        timer.stop();
        timer.resume();
        frames.tick(2000);

        expect(onEnd).not.toHaveBeenCalled();
    });
});
