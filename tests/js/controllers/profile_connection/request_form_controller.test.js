import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';

const RequestFormController = (await import('../../../../assets/controllers/profile_connection/request_form_controller.js')).default;

describe('profile-connection--request-form controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = `
            <section data-controller="profile-connection--request-form"
                      data-profile-connection--request-form-help-message-value="Saisis l'alias suivi du code"
                      data-profile-connection--request-form-sharing-required-hint-value="Active le partage de ton profil ci-dessus">
                <p data-profile-connection--request-form-target="hint">Active le partage de ton profil ci-dessus</p>
                <input type="text" disabled data-profile-connection--request-form-target="input">
                <button type="submit" disabled data-profile-connection--request-form-target="submit">Envoyer</button>
            </section>
        `;

        application = Application.start();
        application.register('profile-connection--request-form', RequestFormController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    it('enables the input and button and swaps the hint when sharing is turned on', () => {
        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: true },
        }));

        expect(document.querySelector('input').disabled).toBe(false);
        expect(document.querySelector('button').disabled).toBe(false);
        expect(document.querySelector('[data-profile-connection--request-form-target="hint"]').textContent).toBe("Saisis l'alias suivi du code");
    });

    it('disables the input and button and swaps the hint when sharing is turned off', () => {
        document.querySelector('input').disabled = false;
        document.querySelector('button').disabled = false;

        window.dispatchEvent(new CustomEvent('profile-connection:sharing-changed', {
            detail: { isDiscoverable: false },
        }));

        expect(document.querySelector('input').disabled).toBe(true);
        expect(document.querySelector('button').disabled).toBe(true);
        expect(document.querySelector('[data-profile-connection--request-form-target="hint"]').textContent).toBe('Active le partage de ton profil ci-dessus');
    });
});
