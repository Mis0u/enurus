import { Controller } from '@hotwired/stimulus';
import { editClasses } from './passwordRule/_editClasses.js';

export default class extends Controller {
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
        const rules = this.#evaluateRules();
        this.#updateRuleIndicators(rules);
        this.submitButtonTarget.disabled = !this.#isFormValid(rules);
    }

    #evaluateRules() {
        const password = this.inputPasswordTarget.value;
        const minLength = parseInt(this.inputPasswordTarget.dataset.minLength, 10);
        const specialChars = this.inputPasswordTarget.dataset.specialChars ?? '';
        const specialCharPattern = new RegExp(`[${this.#escapeForCharClass(specialChars)}]`);

        return {
            isMinLengthValid: password.length >= minLength,
            hasUpperAndLowerCase: /[A-Z]/.test(password) && /[a-z]/.test(password),
            hasNumber: /[0-9]/.test(password),
            hasSpecialChar: specialCharPattern.test(password),
            isRepeatedPasswordIdentical: this.#isRepeatedPasswordIdentical(password),
        };
    }

    #isRepeatedPasswordIdentical(password) {
        if (!this.hasInputRepeatPasswordTarget) {
            return null;
        }

        const repeatedPassword = this.inputRepeatPasswordTarget.value;

        return password === repeatedPassword && repeatedPassword.length > 0;
    }

    #updateRuleIndicators(rules) {
        editClasses(rules.isMinLengthValid, this.minLengthRuleTarget);
        editClasses(rules.hasUpperAndLowerCase, this.upperLowerRuleTarget);
        editClasses(rules.hasNumber, this.numberRuleTarget);
        editClasses(rules.hasSpecialChar, this.specialCharRuleTarget);

        if (this.hasInputRepeatPasswordTarget) {
            editClasses(rules.isRepeatedPasswordIdentical, this.identicalPasswordRuleTarget);
        }
    }

    #isFormValid(rules) {
        const baseRulesValid = rules.isMinLengthValid
            && rules.hasUpperAndLowerCase
            && rules.hasNumber
            && rules.hasSpecialChar;

        if (this.hasInputRepeatPasswordTarget) {
            return baseRulesValid && rules.isRepeatedPasswordIdentical;
        }

        return baseRulesValid;
    }

    #escapeForCharClass(chars) {
        return chars.replace(/[\]\\^-]/g, '\\$&');
    }
}
