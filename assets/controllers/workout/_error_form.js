const ERROR_BORDER_CLASS = '!border-[rgba(244,63,94,0.6)]';

export function handleErrorField(exerciseListTarget) {
    let valid = true;
    exerciseListTarget.querySelectorAll('input[required]:not(:disabled)').forEach(input => {
        if (isFieldInvalid(input)) {
            markFieldInvalid(input);
            valid = false;
        } else {
            clearFieldError(input);
        }
    });
    return valid;
}

export function clearFieldError(input) {
    input.classList.remove(ERROR_BORDER_CLASS);
    input.removeAttribute('aria-invalid');
    input.nextElementSibling?.classList.contains('js-error-message') && input.nextElementSibling.remove();
}

// Centré plutôt qu'en haut/bas d'écran : sur mobile, la barre « Valider » et la barre de
// navigation fixes masquent le bas de la page — un champ simplement « rendu visible » peut rester
// caché dessous. Le focus ouvre directement le clavier numérique sur le champ à compléter.
export function revealFirstInvalidField(exerciseListTarget) {
    const firstInvalid = exerciseListTarget.querySelector('[aria-invalid="true"]');
    if (!firstInvalid) {
        return;
    }

    firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
    firstInvalid.focus({ preventScroll: true });
}

function isFieldInvalid(input) {
    const isEmpty  = input.value === '' || input.value === null;
    const minValue = input.hasAttribute('min') ? parseFloat(input.min) : null;

    return isEmpty || (minValue !== null && parseFloat(input.value) < minValue);
}

function markFieldInvalid(input) {
    input.classList.add(ERROR_BORDER_CLASS);
    input.setAttribute('aria-invalid', 'true');
    if (input.nextElementSibling?.classList.contains('js-error-message')) {
        return;
    }

    const error       = document.createElement('p');
    error.className   = 'js-error-message text-[11px] text-[#f43f5e] mt-1';
    error.textContent = input.dataset.errorMessage;
    input.insertAdjacentElement('afterend', error);
}
