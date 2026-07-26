// assets/controllers/routine/routine-exercise-filter.js
//
// Filtrage combiné (recherche texte + muscles) partagé entre les controllers create et edit.
// Un exercice correspond si son nom matche la recherche ET si, pour au moins un des filtres
// muscle actifs (OR), le muscle est bien assigné avec le type demandé (primaire ou secondaire)
// sur cet exercice.

/** Insensible à la casse et aux accents, pour que "developpe" trouve "Développé". */
export function normalizeForSearch(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}

export function matchesFilters(data, searchQuery, muscleFilters) {
    const matchesSearch = !searchQuery || data.normalizedName.includes(searchQuery);
    const matchesMuscle = muscleFilters.length === 0 || muscleFilters.some(({ id, type }) => {
        const ids = type === 'primary' ? data.primaryMuscleGroupIds : data.secondaryMuscleGroupIds;
        return ids.includes(id);
    });

    return matchesSearch && matchesMuscle;
}
