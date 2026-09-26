// Brouillon de la séance en cours d'enregistrement, gardé dans le navigateur (localStorage) : il
// survit à un rechargement de la PWA, un geste retour ou un détour par la création d'un exercice.
// Une clé par utilisateur, pour qu'un appareil partagé ne montre jamais la saisie d'un autre.
// Le stockage peut être indisponible (navigation privée, quota) : le brouillon est alors
// silencieusement perdu, jamais une erreur qui bloquerait la saisie.

const KEY_PREFIX = 'enurus:workout-draft:';

// Compté depuis la dernière modification : une longue saisie n'expire jamais en cours de route.
export const DRAFT_TTL_MS = 60 * 60 * 1000;

export function saveDraft(userId, draft, now = Date.now()) {
    safely(() => localStorage.setItem(keyOf(userId), JSON.stringify({ ...draft, savedAt: now })));
}

export function loadDraft(userId, now = Date.now()) {
    const draft = parse(safely(() => localStorage.getItem(keyOf(userId))));

    if (!isFresh(draft, now)) {
        clearDraft(userId);
        return null;
    }

    return draft;
}

export function clearDraft(userId) {
    safely(() => localStorage.removeItem(keyOf(userId)));
}

export function purgeAllDrafts() {
    safely(() => {
        Object.keys(localStorage)
            .filter((key) => key.startsWith(KEY_PREFIX))
            .forEach((key) => localStorage.removeItem(key));
    });
}

function keyOf(userId) {
    return `${KEY_PREFIX}${userId}`;
}

function isFresh(draft, now) {
    return draft !== null
        && typeof draft.savedAt === 'number'
        && now - draft.savedAt < DRAFT_TTL_MS
        && Array.isArray(draft.exercises);
}

function parse(json) {
    if (typeof json !== 'string') {
        return null;
    }

    try {
        const draft = JSON.parse(json);
        return typeof draft === 'object' ? draft : null;
    } catch {
        return null;
    }
}

function safely(operation) {
    try {
        return operation();
    } catch {
        return null;
    }
}
