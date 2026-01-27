import { Controller } from '@hotwired/stimulus';
import { editClasses } from './passwordRule/_editClasses.js';
export default class extends Controller {
    /** @type {string[]} */
    static targets = [
        'inputPassword',
        'inputRepeatPassword',
        'submitButton',
        'minLengthRule',
        'upperLowerRule',
        'numberRule',
        'identicalPasswordRule',
        'specialCharRule',
    ];

    validate() {
        /** @type {string} */
        const password = this.inputPasswordTarget.value;

        /** @type {string} */
        const repeatedPassword = this.inputRepeatPasswordTarget.value;

        /** @type {number} */
        const minLength = parseInt(this.inputPasswordTarget.dataset.minLength);

        /** @type {boolean} */
        const isMinLengthValid = password.length >= minLength;

        /** @type {boolean} */
        const containsUpperCase = /[A-Z]/.test(password);

        /** @type {boolean} */
        const containsLowerCase = /[a-z]/.test(password);

        /** @type {boolean} */
        const containsNumber = /[0-9]/.test(password);

        /** @type {boolean} */
        const hasSpecialChar = /[!?%$*&]/.test(password);

        /** @type {boolean} */
        const containsUpperAndLowerCase = containsUpperCase && containsLowerCase;

        /** @type {boolean} */
        const isPasswordEqualRepeatedPassword = password === repeatedPassword && repeatedPassword.length > 0;

        editClasses(isMinLengthValid, this.minLengthRuleTarget);
        editClasses(containsUpperAndLowerCase, this.upperLowerRuleTarget);
        editClasses(containsNumber, this.numberRuleTarget);
        editClasses(hasSpecialChar, this.specialCharRuleTarget);
        editClasses(isPasswordEqualRepeatedPassword, this.identicalPasswordRuleTarget);

        /** @type {boolean} */
        const isPasswordValid = isMinLengthValid && containsUpperAndLowerCase && containsNumber && hasSpecialChar && isPasswordEqualRepeatedPassword;

        /** @type {boolean} */
        this.submitButtonTarget.disabled = !isPasswordValid;
    }
}
