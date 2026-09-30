import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { Application } from '@hotwired/stimulus';
import StoryController from '../../../../assets/controllers/year_in_review/story_controller.js';
import { StoryTimer } from '../../../../assets/controllers/year_in_review/story_timer.js';

function nextTick() {
    return new Promise((resolve) => setTimeout(resolve, 0));
}

function stubReducedMotion(reduced) {
    window.matchMedia = vi.fn().mockReturnValue({ matches: reduced });
}

function buildDom() {
    document.body.innerHTML = `
        <div data-controller="year-in-review--story"
             data-action="keydown@window->year-in-review--story#keydown"
             data-year-in-review--story-duration-value="10000"
             data-year-in-review--story-close-url-value="/fr/mes-resumes"
             data-year-in-review--story-pause-label-value="Mettre en pause"
             data-year-in-review--story-resume-label-value="Reprendre"
             data-year-in-review--story-status-template-value="Écran __position__ sur __count__">
            <span data-year-in-review--story-target="bar"></span>
            <span data-year-in-review--story-target="bar"></span>
            <span data-year-in-review--story-target="bar"></span>
            <button class="pause" data-year-in-review--story-target="pauseButton" data-action="year-in-review--story#togglePause"></button>
            <main data-year-in-review--story-target="track" data-action="click->year-in-review--story#tap">
                <section data-year-in-review--story-target="screen" tabindex="-1">1</section>
                <section data-year-in-review--story-target="screen" tabindex="-1">2 <a href="/x">lien</a></section>
                <section data-year-in-review--story-target="screen" tabindex="-1">3</section>
            </main>
            <button class="previous" data-action="year-in-review--story#previous" data-year-in-review--story-target="previousButton"></button>
            <button class="next" data-action="year-in-review--story#next" data-year-in-review--story-target="nextButton"></button>
            <p data-year-in-review--story-target="status"></p>
        </div>
    `;
}

const visibleScreens = () => [...document.querySelectorAll('section')].filter((screen) => ! screen.hidden).map((screen) => screen.textContent.trim().charAt(0));
const status = () => document.querySelector('[data-year-in-review--story-target="status"]').textContent;
const track = () => document.querySelector('main');

function tapAt(ratio) {
    track().getBoundingClientRect = () => ({ left: 0, width: 300 });
    track().dispatchEvent(new MouseEvent('click', { bubbles: true, clientX: 300 * ratio }));
}

describe('year-in-review--story controller', () => {
    let application;

    async function start({ reducedMotion = false } = {}) {
        stubReducedMotion(reducedMotion);
        buildDom();
        application = Application.start();
        application.register('year-in-review--story', StoryController);
        await nextTick();
    }

    beforeEach(() => {
        vi.spyOn(StoryTimer.prototype, 'start');
        vi.spyOn(StoryTimer.prototype, 'pause');
        vi.spyOn(StoryTimer.prototype, 'resume');
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    it('shows one screen at a time and announces its position', async () => {
        await start();

        expect(visibleScreens()).toEqual(['1']);
        expect(status()).toBe('Écran 1 sur 3');
        expect(document.querySelector('[data-controller]').dataset.story).toBe('on');
    });

    it('moves with the buttons, never before the first nor past the last screen', async () => {
        await start();

        document.querySelector('.previous').click();
        expect(visibleScreens()).toEqual(['1']);
        document.querySelector('.next').click();
        document.querySelector('.next').click();
        document.querySelector('.next').click();

        expect(visibleScreens()).toEqual(['3']);
        expect(status()).toBe('Écran 3 sur 3');
    });

    it('hides previous on the first screen and next on the last one', async () => {
        await start();
        const previous = document.querySelector('.previous');
        const next = document.querySelector('.next');

        expect(previous.style.visibility).toBe('hidden');
        expect(next.style.visibility).toBe('');
        next.click();
        next.click();

        expect(previous.style.visibility).toBe('');
        expect(next.style.visibility).toBe('hidden');
    });

    it('moves with the arrow keys and the space bar', async () => {
        await start();

        window.dispatchEvent(new KeyboardEvent('keydown', { key: ' ' }));
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowLeft' }));

        expect(visibleScreens()).toEqual(['2']);
    });

    it('goes back on a tap in the left third and forward elsewhere', async () => {
        await start();

        tapAt(0.8);
        tapAt(0.8);
        tapAt(0.1);

        expect(visibleScreens()).toEqual(['2']);
    });

    it('lets a tap on a link do its own job', async () => {
        await start();
        document.querySelector('.next').click();

        document.querySelector('section a').addEventListener('click', (event) => event.preventDefault());
        document.querySelector('section a').click();

        expect(visibleScreens()).toEqual(['2']);
    });

    it('pauses and resumes with the pause button, whose label follows', async () => {
        await start();
        const pauseButton = document.querySelector('.pause');

        pauseButton.click();
        expect(StoryTimer.prototype.pause).toHaveBeenCalled();
        expect(pauseButton.getAttribute('aria-pressed')).toBe('true');
        expect(pauseButton.getAttribute('aria-label')).toBe('Reprendre');

        pauseButton.click();
        expect(StoryTimer.prototype.resume).toHaveBeenCalled();
        expect(pauseButton.getAttribute('aria-label')).toBe('Mettre en pause');
    });

    it('offers no pause where no timer runs', async () => {
        await start();
        const pauseButton = document.querySelector('.pause');

        expect(pauseButton.style.visibility).toBe('');
        document.querySelector('.next').click();
        document.querySelector('.next').click();

        expect(pauseButton.style.visibility).toBe('hidden');
    });

    it('never runs a timer on the last screen', async () => {
        await start();
        StoryTimer.prototype.start.mockClear();

        document.querySelector('.next').click();
        document.querySelector('.next').click();

        expect(StoryTimer.prototype.start).toHaveBeenCalledOnce();
    });

    it('never advances on its own when reduced motion is requested', async () => {
        await start({ reducedMotion: true });

        expect(StoryTimer.prototype.start).not.toHaveBeenCalled();
        expect(document.querySelector('.pause').style.visibility).toBe('hidden');
    });

    it('pauses while the tab is hidden', async () => {
        await start();

        Object.defineProperty(document, 'hidden', { configurable: true, get: () => true });
        document.dispatchEvent(new Event('visibilitychange'));
        expect(StoryTimer.prototype.pause).toHaveBeenCalled();

        Object.defineProperty(document, 'hidden', { configurable: true, get: () => false });
        document.dispatchEvent(new Event('visibilitychange'));
        expect(StoryTimer.prototype.resume).toHaveBeenCalled();
    });
});
