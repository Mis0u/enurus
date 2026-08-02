// assets/utils/search.js

/** Insensible à la casse et aux accents, pour que "developpe" trouve "Développé". */
export function normalizeForSearch(value) {
    return value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase();
}
