// Passage entre le formulaire d'enregistrement de séance et son brouillon (cf. draft_storage.js).
// Les séries sont gardées comme données brutes, jamais comme HTML : à la restauration, les cartes
// sont rendues à nouveau côté serveur (WorkoutDraftExercisesBlockController), à jour même si le
// gabarit a changé entre-temps.

const SET_FIELDS = ['weight', 'reps', 'duration', 'distance'];

export function readDraftFromForm(root) {
    return {
        date: root.querySelector('#workout_performedAt')?.value ?? '',
        duration: root.querySelector('#workout_duration')?.value ?? '',
        routineId: readRoutineId(root),
        exercises: [...root.querySelectorAll('#exercises-list > [data-exercise-index]')].map(readExercise),
    };
}

export function restoreDraftFields(root, draft) {
    restoreDate(root.querySelector('#workout_performedAt'), draft.date);
    restoreValue(root.querySelector('#workout_duration'), draft.duration);
    restoreRoutine(root, draft.routineId);
}

function readRoutineId(root) {
    const isRoutineSession = !root.querySelector('#routine-selector')?.classList.contains('hidden');

    return isRoutineSession ? (root.querySelector('#workout-routine')?.value ?? '') : '';
}

// Une carte bloquée (exercice au poids de corps sans poids renseigné) n'a que des champs
// désactivés : sans série, elle revient bloquée à la restauration. `prefilled` : la carte montre
// encore « Repris de ta séance du… » (jamais effacée), mention et bouton « Effacer » à rétablir.
function readExercise(card) {
    const isBlocked = card.dataset.bodyweightBlocked === 'true';

    return {
        exerciseId: card.querySelector('input[name$="[exercise]"]').value,
        prefilled: card.querySelector('.js-prefilled-from') !== null,
        sets: isBlocked ? [] : [...card.querySelectorAll('.js-sets-tbody > tr')].map(readSet),
    };
}

function readSet(row) {
    return Object.fromEntries(SET_FIELDS.map((field) => [field, readNumber(row, field)]));
}

function readNumber(row, field) {
    const value = row.querySelector(`input[name$="[${field}]"]`)?.value ?? '';
    const number = Number(value);

    return value === '' || !Number.isFinite(number) ? null : number;
}

// Sans événement `change` : ni vérification de doublon de date (faite de toute façon au clic sur
// Valider), ni sauvegarde en boucle du brouillon pendant sa propre restauration.
function restoreDate(input, date) {
    if (!input || !date) {
        return;
    }

    if (input._flatpickr) {
        input._flatpickr.setDate(date, false);
    } else {
        input.value = date;
    }
}

function restoreValue(input, value) {
    if (input && value) {
        input.value = value;
    }
}

// Valeur posée sans événement `change` : le chargement des exercices de la routine
// (routine-loader_controller.js) écraserait ceux du brouillon.
function restoreRoutine(root, routineId) {
    const select = root.querySelector('#workout-routine');
    const routineExists = select && [...select.options].some((option) => option.value === routineId);

    if (!routineId || !routineExists) {
        return;
    }

    root.querySelector('[data-session-type-target="routine"]')?.click();
    select.value = routineId;
}
