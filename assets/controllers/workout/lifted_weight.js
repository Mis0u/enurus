/**
 * Indicateur "poids soulevé" (part de poids de corps + lest saisi) affiché sous l'input lest
 * d'un exercice au poids de corps. La part de poids de corps est toujours fournie par le serveur
 * via `data-bodyweight-share` (série existante : figée sur son `bodyweightSnapshotKg` ; nouvelle
 * série : poids de corps actuel de l'utilisateur) — ce module ne fait qu'additionner le lest saisi,
 * jamais de calcul de pourcentage ou de conversion d'unité côté JS.
 */
export function initLiftedWeights(root, template) {
    root.querySelectorAll('.js-lifted-weight').forEach((el) => updateLiftedWeight(el, template));
}

export function updateLiftedWeight(el, template) {
    const weightInput = el.closest('td')?.querySelector('input[type="number"]');
    const share = parseFloat(el.dataset.bodyweightShare) || 0;
    const lest = parseFloat(weightInput?.value) || 0;

    el.textContent = template.replace('__WEIGHT__', (share + lest).toFixed(1));
}

export function updateLiftedWeightForInput(input, template) {
    const el = input.closest('td')?.querySelector('.js-lifted-weight');
    if (el) {
        updateLiftedWeight(el, template);
    }
}
