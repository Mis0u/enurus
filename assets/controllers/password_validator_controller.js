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

        editClasses(isMinLengthValid, this.minLengthRuleTarget);
        editClasses(containsUpperAndLowerCase, this.upperLowerRuleTarget);
        editClasses(containsNumber, this.numberRuleTarget);
        editClasses(hasSpecialChar, this.specialCharRuleTarget);

        let isPasswordValid;
        if (this.hasInputRepeatPasswordTarget){
            /** @type {string} */
            const repeatedPassword = this.inputRepeatPasswordTarget.value;
            /** @type {boolean} */
            const isPasswordEqualRepeatedPassword = password === repeatedPassword && repeatedPassword.length > 0;
            editClasses(isPasswordEqualRepeatedPassword, this.identicalPasswordRuleTarget);
            isPasswordValid = isMinLengthValid && containsUpperAndLowerCase && containsNumber && hasSpecialChar && isPasswordEqualRepeatedPassword;
        } else {
            isPasswordValid = isMinLengthValid && containsUpperAndLowerCase && containsNumber && hasSpecialChar;
        }

        /** @type {boolean} */
        this.submitButtonTarget.disabled = !isPasswordValid;
    }
}
