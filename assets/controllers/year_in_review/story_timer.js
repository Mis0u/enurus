// Minuteur d'un écran du résumé annuel : progression de 0 à 1 sur `duration`, mise en pause sans
// perdre le temps écoulé (bouton pause, appui long, onglet caché). Horloge et planificateur
// injectables pour les tests ; par défaut `performance.now()` et `requestAnimationFrame`.
export class StoryTimer {
    #duration;
    #onProgress;
    #onEnd;
    #now;
    #schedule;
    #cancel;
    #elapsedBeforePause = 0;
    #startedAt = null;
    #frame = null;
    #stopped = true;

    constructor({
        duration,
        onProgress,
        onEnd,
        now = () => performance.now(),
        schedule = (callback) => requestAnimationFrame(callback),
        cancel = (frame) => cancelAnimationFrame(frame),
    }) {
        this.#duration = duration;
        this.#onProgress = onProgress;
        this.#onEnd = onEnd;
        this.#now = now;
        this.#schedule = schedule;
        this.#cancel = cancel;
    }

    start() {
        this.stop();
        this.#elapsedBeforePause = 0;
        this.#stopped = false;
        this.#run();
    }

    pause() {
        if (null === this.#startedAt) {
            return;
        }

        this.#elapsedBeforePause += this.#now() - this.#startedAt;
        this.#startedAt = null;
        this.#cancelFrame();
    }

    resume() {
        if (! this.#stopped && null === this.#startedAt) {
            this.#run();
        }
    }

    stop() {
        this.#stopped = true;
        this.#startedAt = null;
        this.#cancelFrame();
    }

    #run() {
        this.#startedAt = this.#now();
        this.#frame = this.#schedule(() => this.#tick());
    }

    #tick() {
        const progress = Math.min(1, (this.#elapsedBeforePause + this.#now() - this.#startedAt) / this.#duration);
        this.#onProgress(progress);

        if (1 > progress) {
            this.#frame = this.#schedule(() => this.#tick());

            return;
        }

        this.stop();
        this.#onEnd();
    }

    #cancelFrame() {
        if (null !== this.#frame) {
            this.#cancel(this.#frame);
            this.#frame = null;
        }
    }
}
