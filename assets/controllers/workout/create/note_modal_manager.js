import { swalError } from '../../swal/error/_error.js';
import { showSpinner, hideSpinner } from '../../../utils/submit_spinner.js';

export class NoteModalManager {
    #application;
    #uploadPhotoUrl;
    #submitFailedTitle;
    #submitFailedText;

    constructor(application, uploadPhotoUrl, submitFailedTitle, submitFailedText) {
        this.#application = application;
        this.#uploadPhotoUrl = uploadPhotoUrl;
        this.#submitFailedTitle = submitFailedTitle;
        this.#submitFailedText = submitFailedText;
        this.#bindBack();
        this.#bindSubmit();
    }

    open() {
        const modal = this.#getModal();
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        this.#resetTextarea();
    }

    // ─── Privé ───────────────────────────────────────────────────

    #getModal() {
        return document.getElementById('note-modal');
    }

    #getTextarea() {
        return document.getElementById('workout-note-input');
    }

    #resetTextarea() {
        this.#getTextarea().value = '';
    }

    #closeModal() {
        const modal = this.#getModal();
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    #bindBack() {
        const backBtn = document.getElementById('note-modal-back');
        backBtn.addEventListener('click', () => this.#closeModal());
    }

    #bindSubmit() {
        const submitBtn = document.getElementById('note-modal-submit');
        submitBtn.addEventListener('click', async () => {
            await this.#handleSubmit(submitBtn);
        });
    }

    async #handleSubmit(submitBtn) {
        this.#injectNote();

        const form = document.querySelector('form');

        if (!form.reportValidity()) {
            this.#closeModal();
            return;
        }

        // Empêche toute soumission concurrente pendant l'enregistrement (impatience de l'utilisateur
        // sur ce qui peut prendre plusieurs secondes : requête réseau puis upload photo éventuel) —
        // sans ça, chaque clic supplémentaire créait une nouvelle séance en doublon.
        showSpinner(submitBtn);

        const { workoutId, redirectUrl } = await this.#submitForm();
        if (!workoutId || !redirectUrl) {
            hideSpinner(submitBtn);
            swalError(this.#submitFailedTitle, this.#submitFailedText, '#0f1928', '#f0f4ff', '#f43f5e');
            return;
        }
        await this.#uploadPhotoIfSelected(workoutId);
        window.location.href = redirectUrl;
    }

    #injectNote() {
        const noteInput = document.getElementById('workout_note');
        if (noteInput) {
            noteInput.value = this.#getTextarea().value.trim();
        }
    }

    async #submitForm() {
        const form = document.querySelector('form');
        const formData = new FormData(form);

        const tokenInput = form.querySelector('input[name="workout[_token]"]');
        if (tokenInput) {
            formData.set('workout[_token]', tokenInput.value);
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });

            if (!response.ok) {
                const data = await response.json();
                console.error('Erreur création séance :', data.error);
                return { workoutId: null, redirectUrl: null };
            }

            const data = await response.json();
            return { workoutId: data.id, redirectUrl: data.redirectUrl };
        } catch (e) {
            console.error('Erreur réseau :', e);
            return { workoutId: null, redirectUrl: null };
        }
    }

    async #uploadPhotoIfSelected(workoutId) {
        const modal = this.#getModal();
        const photoUploadEl = modal.querySelector('[data-controller="workout--photo-upload"]');

        if (!photoUploadEl) {
            return;
        }

        const photoController = this.#application.getControllerForElementAndIdentifier(
            photoUploadEl,
            'workout--photo-upload'
        );

        if (!photoController) {
            return;
        }

        photoController.uploadUrlValue = this.#uploadPhotoUrl.replace('__ID__', workoutId);
        await photoController.uploadIfSelected();
    }
}
