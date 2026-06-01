export function handleErrorField(exerciseListTarget) {
    let valid = true;
    exerciseListTarget.querySelectorAll('input[required]').forEach(input => {
        const isEmpty    = input.value === '' || input.value === null;
        const minValue   = input.hasAttribute('min') ? parseFloat(input.min) : null;
        const isBelowMin = minValue !== null && input.value !== '' && parseFloat(input.value) < minValue;

        if (isEmpty || isBelowMin) {
            input.classList.add('!border-[rgba(244,63,94,0.6)]');
            if (!input.nextElementSibling?.classList.contains('js-error-message')) {
                const error       = document.createElement('p');
                error.className   = 'js-error-message text-[11px] text-[#f43f5e] mt-1';
                error.textContent = input.dataset.errorMessage;
                input.insertAdjacentElement('afterend', error);
            }
            valid = false;
        } else {
            input.classList.remove('!border-[rgba(244,63,94,0.6)]');
            input.nextElementSibling?.classList.contains('js-error-message') && input.nextElementSibling.remove();
        }
    });
    return valid;
}
