import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { Application } from '@hotwired/stimulus';
import PasswordValidatorController from '../../../assets/controllers/password_validator_controller.js';

function nextTick() {
    return new Promise(resolve => setTimeout(resolve, 0));
}

function fullFormMarkup() {
    return `
        <form data-controller="password-validator">
            <input data-password-validator-target="inputPassword"
                   data-min-length="8"
                   data-special-chars="!@#$%">
            <input data-password-validator-target="inputRepeatPassword">
            <button data-password-validator-target="submitButton" type="submit">Submit</button>

            <div data-password-validator-target="minLengthRule"><svg></svg></div>
            <div data-password-validator-target="upperLowerRule"><svg></svg></div>
            <div data-password-validator-target="numberRule"><svg></svg></div>
            <div data-password-validator-target="identicalPasswordRule"><svg></svg></div>
            <div data-password-validator-target="specialCharRule"><svg></svg></div>
        </form>
    `;
}

describe('password_validator_controller', () => {
    let application;

    beforeEach(() => {
        document.body.innerHTML = fullFormMarkup();
        application = Application.start();
        application.register('password-validator', PasswordValidatorController);
    });

    afterEach(() => {
        application.stop();
        document.body.innerHTML = '';
    });

    function getController() {
        const el = document.querySelector('[data-controller="password-validator"]');

        return application.getControllerForElementAndIdentifier(el, 'password-validator');
    }

    it('disables the submit button while the password does not satisfy every rule', async () => {
        await nextTick();
        const controller = getController();
        const passwordInput = document.querySelector('[data-password-validator-target="inputPassword"]');
        const submitButton = document.querySelector('[data-password-validator-target="submitButton"]');

        passwordInput.value = 'short';
        controller.validate();

        expect(submitButton.disabled).toBe(true);
    });

    it('enables the submit button once every rule and the repeated password match', async () => {
        await nextTick();
        const controller = getController();
        const passwordInput = document.querySelector('[data-password-validator-target="inputPassword"]');
        const repeatInput = document.querySelector('[data-password-validator-target="inputRepeatPassword"]');
        const submitButton = document.querySelector('[data-password-validator-target="submitButton"]');

        passwordInput.value = 'Str0ng!Pass';
        repeatInput.value = 'Str0ng!Pass';
        controller.validate();

        expect(submitButton.disabled).toBe(false);
    });

    it('keeps the submit button disabled when the repeated password does not match', async () => {
        await nextTick();
        const controller = getController();
        const passwordInput = document.querySelector('[data-password-validator-target="inputPassword"]');
        const repeatInput = document.querySelector('[data-password-validator-target="inputRepeatPassword"]');
        const submitButton = document.querySelector('[data-password-validator-target="submitButton"]');

        passwordInput.value = 'Str0ng!Pass';
        repeatInput.value = 'Different1!';
        controller.validate();

        expect(submitButton.disabled).toBe(true);
    });

    it('does not require a repeated password when the repeat target is absent', async () => {
        document.body.innerHTML = `
            <form data-controller="password-validator">
                <input data-password-validator-target="inputPassword"
                       data-min-length="8"
                       data-special-chars="!@#$%">
                <button data-password-validator-target="submitButton" type="submit">Submit</button>
                <div data-password-validator-target="minLengthRule"><svg></svg></div>
                <div data-password-validator-target="upperLowerRule"><svg></svg></div>
                <div data-password-validator-target="numberRule"><svg></svg></div>
                <div data-password-validator-target="specialCharRule"><svg></svg></div>
            </form>
        `;
        application.stop();
        application = Application.start();
        application.register('password-validator', PasswordValidatorController);
        await nextTick();

        const controller = getController();
        const passwordInput = document.querySelector('[data-password-validator-target="inputPassword"]');
        const submitButton = document.querySelector('[data-password-validator-target="submitButton"]');

        passwordInput.value = 'Str0ng!Pass';
        controller.validate();

        expect(submitButton.disabled).toBe(false);
    });

    it('does not break when data-special-chars contains regex-special characters', async () => {
        document.body.innerHTML = `
            <form data-controller="password-validator">
                <input data-password-validator-target="inputPassword"
                       data-min-length="4"
                       data-special-chars="]\\^-">
                <button data-password-validator-target="submitButton" type="submit">Submit</button>
                <div data-password-validator-target="minLengthRule"><svg></svg></div>
                <div data-password-validator-target="upperLowerRule"><svg></svg></div>
                <div data-password-validator-target="numberRule"><svg></svg></div>
                <div data-password-validator-target="specialCharRule"><svg></svg></div>
            </form>
        `;
        application.stop();
        application = Application.start();
        application.register('password-validator', PasswordValidatorController);
        await nextTick();

        const controller = getController();
        const passwordInput = document.querySelector('[data-password-validator-target="inputPassword"]');
        const submitButton = document.querySelector('[data-password-validator-target="submitButton"]');

        passwordInput.value = 'Aa1]';

        expect(() => controller.validate()).not.toThrow();
        expect(submitButton.disabled).toBe(false);
    });
});
