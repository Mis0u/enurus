// assets/utils/submit_spinner.js
//
// Marquage visuel partagé entre form--submit-spinner (POST classique, jamais masqué : la page
// navigue ou le DOM est reconstruit) et les controllers de soumission Ajax (settings--password-submit)
// qui doivent au contraire le retirer une fois la requête résolue.

const SPINNER_CLASS = 'inline-block w-4 h-4 ml-2 align-middle border-2 border-white/30 border-t-white rounded-full animate-spin';

export function showSpinner(button) {
    button.disabled = true;
    button.classList.add('cursor-wait');

    const spinner = document.createElement('span');
    spinner.className = SPINNER_CLASS;
    spinner.dataset.submitSpinner = '';
    spinner.setAttribute('aria-hidden', 'true');
    button.appendChild(spinner);
}

export function hideSpinner(button) {
    button.classList.remove('cursor-wait');
    button.querySelector('[data-submit-spinner]')?.remove();
}
