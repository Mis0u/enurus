// Brouillon auto-enregistré de la séance en cours (page d'enregistrement uniquement) : chaque
// saisie est gardée dans le navigateur, et restaurée au retour sur la page — après un
// rechargement de la PWA, un appel, un geste retour, ou un détour par la création d'un exercice
// manquant (paramètre `addedExercise` : l'exercice créé est ajouté à la fin de la séance).

import { Controller } from '@hotwired/stimulus';
import { clearDraft, loadDraft, saveDraft } from './draft_storage.js';
import { readDraftFromForm, restoreDraftFields } from './draft_form.js';

const SAVE_DELAY_MS = 500;
const ADDED_EXERCISE_PARAM = 'addedExercise';

// Changements de structure (cartes ou séries ajoutées, retirées, déplacées) : ils ne passent
// jamais par un événement `input`/`change` de formulaire.
const STRUCTURE_EVENTS = ['exercise:selected', 'routine:exercises-loaded', 'workout:changed'];

export default class extends Controller {
    static targets = ['banner'];

    static values = {
        userId: String,
        blockUrl: String,
    };

    #saveTimer = null;
    // Aucune sauvegarde tant que la restauration n'est pas finie (elle écraserait le brouillon
    // avec un formulaire encore vide), ni une fois la séance enregistrée ou le brouillon abandonné.
    #frozen = true;

    async connect() {
        this.#listen();
        await this.#restore();
        this.#frozen = false;
    }

    disconnect() {
        this.#flush();
        this.#unlisten();
    }

    discard() {
        this.#freeze();
        window.location.replace(window.location.pathname);
    }

    // ─── Restauration ─────────────────────────────────────────────

    async #restore() {
        const draft = loadDraft(this.userIdValue);
        const exercises = [...(draft?.exercises ?? []), ...this.#takeAddedExercise()];

        if (exercises.length === 0) {
            return;
        }

        const htmls = await this.#fetchCards(exercises);

        // Page remplacée pendant la requête (Turbo) : la nouvelle page restaure elle-même.
        if (!this.element.isConnected) {
            return;
        }

        if (htmls.length > 0) {
            window.dispatchEvent(new CustomEvent('exercise:selected', { detail: { htmls } }));
        }

        if (draft) {
            restoreDraftFields(this.element, draft);
            this.bannerTarget.hidden = false;
        }
    }

    // Retiré de l'URL aussitôt lu : un rechargement ne doit pas ajouter l'exercice une 2e fois
    // (il fait désormais partie du brouillon).
    #takeAddedExercise() {
        const url = new URL(window.location.href);
        const exerciseId = url.searchParams.get(ADDED_EXERCISE_PARAM);

        if (!exerciseId) {
            return [];
        }

        url.searchParams.delete(ADDED_EXERCISE_PARAM);
        window.history.replaceState(window.history.state, '', url);

        return [{ exerciseId, sets: [] }];
    }

    async #fetchCards(exercises) {
        try {
            const response = await fetch(this.blockUrlValue, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ exercises }),
            });

            return response.ok ? (await response.json()).htmls : [];
        } catch {
            return [];
        }
    }

    // ─── Sauvegarde ───────────────────────────────────────────────

    #scheduleSave = () => {
        clearTimeout(this.#saveTimer);
        this.#saveTimer = setTimeout(() => this.#save(), SAVE_DELAY_MS);
    };

    #flush = () => {
        clearTimeout(this.#saveTimer);
        this.#save();
    };

    #flushWhenHidden = () => {
        if (document.visibilityState === 'hidden') {
            this.#flush();
        }
    };

    #save() {
        if (this.#frozen) {
            return;
        }

        const draft = readDraftFromForm(this.element);

        if (draft.exercises.length === 0) {
            clearDraft(this.userIdValue);
            return;
        }

        saveDraft(this.userIdValue, draft);
    }

    #onWorkoutSaved = () => {
        this.#freeze();
    };

    #freeze() {
        this.#frozen = true;
        clearTimeout(this.#saveTimer);
        clearDraft(this.userIdValue);
    }

    // ─── Écouteurs ────────────────────────────────────────────────

    #listen() {
        this.element.addEventListener('input', this.#scheduleSave);
        this.element.addEventListener('change', this.#scheduleSave);
        STRUCTURE_EVENTS.forEach((name) => window.addEventListener(name, this.#scheduleSave));
        window.addEventListener('workout:saved', this.#onWorkoutSaved);
        window.addEventListener('pagehide', this.#flush);
        document.addEventListener('visibilitychange', this.#flushWhenHidden);
    }

    #unlisten() {
        this.element.removeEventListener('input', this.#scheduleSave);
        this.element.removeEventListener('change', this.#scheduleSave);
        STRUCTURE_EVENTS.forEach((name) => window.removeEventListener(name, this.#scheduleSave));
        window.removeEventListener('workout:saved', this.#onWorkoutSaved);
        window.removeEventListener('pagehide', this.#flush);
        document.removeEventListener('visibilitychange', this.#flushWhenHidden);
    }
}
