/**
 * Couleurs partagées pour le coloriage des groupes musculaires sur les silhouettes SVG
 * (bodymap). Source de vérité unique — voir CLAUDE.md section "SVG / silhouette musculaire".
 */
export const MUSCLE_COLORS = {
    none: { fill: '#1e293b', stroke: 'rgba(255,255,255,0.1)', color: '#1e293b' },
    primary: { fill: '#f43f5e', stroke: '#f43f5e', color: '#f43f5e' },
    secondary: { fill: '#06b6d4', stroke: '#06b6d4', color: '#06b6d4' },
};

/**
 * Applique fill/stroke/color à un groupe SVG donné selon son état.
 *
 * @param {Element} group
 * @param {'none'|'primary'|'secondary'} state
 */
export function paintMuscleGroup(group, state) {
    const { fill, stroke, color } = MUSCLE_COLORS[state] ?? MUSCLE_COLORS.none;

    group.style.fill = fill;
    group.style.stroke = stroke;
    group.style.color = color;
}

/**
 * Colore tous les groupes `g#id` correspondant aux svgIds donnés, dans un conteneur.
 *
 * @param {Element} container
 * @param {string[]} ids
 * @param {'none'|'primary'|'secondary'} state
 */
export function paintMuscleGroupsByIds(container, ids, state) {
    ids.forEach(id => {
        container.querySelectorAll(`#${id}`).forEach(el => paintMuscleGroup(el, state));
    });
}

/**
 * Réinitialise toutes les zones `g.bodymap` d'un conteneur à l'état neutre.
 *
 * @param {Element} container
 */
export function resetBodymap(container) {
    container.querySelectorAll('g.bodymap').forEach(g => paintMuscleGroup(g, 'none'));
}

/**
 * Reset + coloriage complet d'une silhouette à partir des IDs primaires/secondaires cumulés
 * de plusieurs exercices. Un muscle primaire pour un exercice et secondaire pour un autre garde
 * la couleur primaire — le rôle le plus engageant l'emporte, jamais l'ordre de peinture.
 *
 * @param {Element} container
 * @param {Iterable<string>} primaryIds
 * @param {Iterable<string>} secondaryIds
 */
export function paintMusclePreview(container, primaryIds, secondaryIds) {
    const primary         = new Set(primaryIds);
    const secondaryOnly   = Array.from(secondaryIds).filter(id => !primary.has(id));

    resetBodymap(container);
    paintMuscleGroupsByIds(container, Array.from(primary), 'primary');
    paintMuscleGroupsByIds(container, secondaryOnly, 'secondary');
}
