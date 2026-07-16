import Swal from 'sweetalert2';

const DEFAULT_THEME = {
    background:         '#0f1928',
    color:              '#f0f4ff',
    confirmButtonColor: '#f43f5e',
    cancelButtonColor:  '#334155',
};

/**
 * Confirmation SweetAlert2 avant suppression — pattern partagé par tous les controllers de
 * suppression (contact, exercise, routine, workout). `options` passe les champs Swal spécifiques
 * à la page (textes traduits, thème) ; `icon`/`showCancelButton` et le thème par défaut sont déjà
 * posés ici.
 *
 * @param {object} options
 * @returns {Promise<boolean>}
 */
export async function confirmDeletion(options) {
    const result = await Swal.fire({
        icon:            'warning',
        showCancelButton: true,
        ...DEFAULT_THEME,
        ...options,
    });

    return result.isConfirmed;
}

/**
 * Envoie la requête DELETE avec le header XHR attendu par les controllers de suppression back-end,
 * et le header CSRF si un token est fourni. N'échoue jamais silencieusement côté appelant : une
 * erreur réseau ou une réponse HTTP non-ok retourne simplement `{ ok: false }`.
 *
 * @param {string} url
 * @param {string} [csrfToken]
 * @returns {Promise<{ok: boolean, data?: any}>}
 */
export async function sendDeleteRequest(url, csrfToken) {
    const headers = { 'X-Requested-With': 'XMLHttpRequest' };

    if (csrfToken) {
        headers['X-CSRF-Token'] = csrfToken;
    }

    try {
        const response = await fetch(url, { method: 'DELETE', headers });

        if (!response.ok) {
            return { ok: false };
        }

        const data = await response.json();

        return { ok: true, data };
    } catch {
        return { ok: false };
    }
}

/**
 * @param {object} options
 */
export function showDeleteError(options) {
    Swal.fire({
        icon: 'error',
        ...DEFAULT_THEME,
        ...options,
    });
}
