// Observé en pratique : navigator.clipboard.writeText() peut résoudre sans lever d'erreur
// tout en n'écrivant rien (permission accordée de façon incohérente selon le contexte) —
// execCommand('copy') sur un textarea temporaire, synchrone et exécuté dans le même geste
// utilisateur que le clic, est essayé en premier car plus fiable ici ; la Clipboard API
// moderne ne sert que de repli si execCommand est indisponible.
export async function copyToClipboard(text) {
    if (legacyCopy(text)) {
        return true;
    }

    if (navigator.clipboard?.writeText) {
        try {
            await navigator.clipboard.writeText(text);

            return true;
        } catch {
            return false;
        }
    }

    return false;
}

function legacyCopy(text) {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let succeeded = false;

    try {
        succeeded = document.execCommand('copy');
    } catch {
        succeeded = false;
    }

    document.body.removeChild(textarea);

    return succeeded;
}
