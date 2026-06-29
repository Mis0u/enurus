// assets/controllers/routine/routine-name-checker.js
//
// Mixin de vérification de nom de routine (disponibilité via fetch).
// Utilisé par create_controller.js et edit_controller.js.
//
// Prérequis dans le controller Stimulus :
//   - target  : nameInput
//   - value   : checkNameUrl (string)
//   - value   : errorNameExists (string)
//   - méthode : setNameAvailable(bool)
//   - méthode : #escape(str) — exposée via escapeHtml(str)

import { buildErrorElement } from './routine-dom-builder.js';

/**
 * À appeler dans connect() du controller.
 * @param {object} controller — instance Stimulus (this)
 */
export function initNameChecker(controller) {
    controller.nameInputTarget.addEventListener('blur', () => {
        checkName(controller);
    });
}

/**
 * Vérifie la disponibilité du nom via fetch.
 * @param {object} controller — instance Stimulus (this)
 */
async function checkName(controller) {
    const name = controller.nameInputTarget.value.trim();

    if (!name) { return; }

    clearNameError(controller);

    const url = new URL(controller.checkNameUrlValue, window.location.origin);
    url.searchParams.set('name', name);

    if (controller.hasExcludeIdValue && controller.excludeIdValue) {
        url.searchParams.set('excludeId', controller.excludeIdValue);
    }

    let available = true;

    try {
        const response = await fetch(url.toString(), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        });

        if (response.ok) {
            const data = await response.json();
            available  = data.available === true;
        }
    } catch {
        // En cas d'erreur réseau, on laisse passer — le backend validera
        return;
    }

    if (!available) {
        showNameError(controller);
    }

    controller.setNameAvailable(available);
}

function showNameError(controller) {
    const field = controller.nameInputTarget;

    clearNameError(controller);
    field.classList.add('border-rose-500');
    field.insertAdjacentElement('afterend', buildErrorElement(controller.errorNameExistsValue));

    field.addEventListener('input', () => {
        clearNameError(controller);
        controller.setNameAvailable(true);
    }, { once: true });
}

function clearNameError(controller) {
    const field = controller.nameInputTarget;
    field.classList.remove('border-rose-500');
    field.parentElement.querySelector('.field-error')?.remove();
}
