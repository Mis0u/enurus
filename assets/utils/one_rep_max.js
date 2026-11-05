// 1RM (charge maximale sur une répétition) estimé par la formule d'Epley, et charges d'entraînement
// qui en découlent. Même formule côté serveur (OneRepMaxCalculator) : les deux sont vérifiées sur
// les mêmes cas, tests/shared/one_rep_max_cases.json.

export const MAX_REPS = 12;

// Au-delà, l'endurance fausse l'estimation : le résultat n'est plus qu'un ordre de grandeur.
export const RELIABLE_REPS = 10;

const EPLEY_DIVISOR = 30;

const TRAINING_PERCENTS = [100, 95, 90, 85, 80, 75, 70, 65, 60];

// Une seule répétition est déjà un 1RM : Epley le gonflerait de 1/30.
export function estimateOneRepMax(weight, reps) {
    if (!(weight > 0) || !Number.isInteger(reps) || reps < 1 || reps > MAX_REPS) {
        return null;
    }

    return reps === 1 ? weight : weight * (1 + reps / EPLEY_DIVISOR);
}

export function trainingLoads(oneRepMax, plateStep) {
    return TRAINING_PERCENTS.map((percent) => ({
        percent,
        load: roundToPlate(oneRepMax * percent / 100, plateStep),
        reps: repsAtPercent(percent),
    }));
}

function roundToPlate(load, plateStep) {
    return Math.round(load / plateStep) * plateStep;
}

// Epley inversée : cohérente avec le 1RM affiché à côté.
function repsAtPercent(percent) {
    return percent === 100 ? 1 : Math.round(EPLEY_DIVISOR * (100 / percent - 1));
}
