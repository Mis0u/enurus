/**
 * File d'attente des popups de célébration (objectifs atteints, badges débloqués) — `Swal.fire()`
 * ferme la popup déjà ouverte : sans file, la seconde écraserait la première quand une même
 * séance atteint un objectif ET débloque un badge. Chaque popup attend la fermeture de la
 * précédente ; l'ordre d'appel (ordre des controllers dans le DOM) est l'ordre d'affichage.
 */
let queue = Promise.resolve();

/**
 * @param {() => (Promise<unknown>|unknown)} showPopup ouvre la popup et renvoie la promesse
 *        résolue à sa fermeture (ex. `() => Swal.fire({...})`)
 * @returns {Promise<void>}
 */
export function enqueueCelebration(showPopup) {
    queue = queue
        .then(() => showPopup())
        .then(() => undefined, () => undefined);

    return queue;
}
