// Lignes de séries d'une carte d'exercice (création et édition de séance) : une nouvelle ligne
// est toujours construite depuis le `<template class="js-set-template">` de la carte, rendu côté
// serveur (cf. templates/workout/create/_template.html.twig) — jamais de HTML dupliqué en JS.

export function insertSetRow(card, tbody) {
    const setIndex = tbody.querySelectorAll('tr').length;
    const html = card.querySelector('.js-set-template').innerHTML
        .replaceAll('__EXERCISE_INDEX__', card.dataset.exerciseIndex)
        .replaceAll('__SET_INDEX__', setIndex)
        .replaceAll('__SET_NUMBER__', setIndex + 1);

    tbody.insertAdjacentHTML('beforeend', html);

    return tbody.lastElementChild;
}

// Abandonne la reprise de la dernière performance : la carte redevient celle d'un exercice
// jamais pratiqué (une seule série vide), sans la mention « Repris de ta séance du… ».
export function resetPrefilledCard(card) {
    const tbody = card.querySelector('.js-sets-tbody');
    tbody.replaceChildren();
    insertSetRow(card, tbody);
    card.querySelector('.js-prefilled-from')?.remove();

    return tbody.firstElementChild;
}
