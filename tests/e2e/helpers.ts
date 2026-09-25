import { expect, type Page } from '@playwright/test';

// Utilisateurs créés par UserFixtures (mot de passe fixture universel, cf. CLAUDE.md).
export const FIXTURE_PASSWORD = 'pass_1234';

export const FIXTURE_USERS = {
    // UserFixtures::WORKOUT_USERS[0] — partagé avec login.spec.ts, ne pas utiliser pour un
    // scénario destructeur (suppression de compte, etc.).
    workout11: { email: 'user-fixture-11-workout@test.com', nickname: 'user-workout-11' },
    // UserFixtures::USER_DASHBOARD_SINGLE — usage dédié, sûr pour un scénario destructeur.
    dashboardSingle: { email: 'user-fixture-1-workout@test.com', nickname: 'user-dashboard-1' },
    // UserFixtures::loadIndexedUsers() — aucune séance, utilisé par aucun autre test : réservé à
    // `workout-duplicate-date.spec.ts`, qui enregistre des séances.
    noWorkout: { email: 'user-fixture-7@test.com', nickname: 'user-fixture-7' },
};

// Le controller Stimulus csrf-protection est chargé en lazy (import dynamique fetché sur le
// réseau) : le listener document qui remplace le placeholder _csrf_token par un vrai token au
// submit (cf. assets/controllers/csrf_protection_controller.js) n'est enregistré qu'une fois ce
// chunk chargé. Un submit avant la fin de ce chargement envoie le placeholder brut → CSRF invalide
// côté serveur → échec silencieux, indiscernable d'un autre problème.
export async function waitForCsrfControllerReady(page: Page): Promise<void> {
    await page.waitForLoadState('networkidle');
}

/**
 * Connecte l'utilisateur via le formulaire de login en `/en/` et attend d'atterrir sur son
 * tableau de bord (LoginSuccessListener redirige vers la locale du compte, 'fr' pour toutes les
 * fixtures existantes — cf. login.spec.ts).
 */
/**
 * Renseigne un champ de date piloté par flatpickr (assets/controllers/workout/date-picker_controller.js)
 * — le `.fill()` natif de Playwright échoue car flatpickr pose `readonly` sur l'input tant que
 * `allowInput` n'est pas activé (comportement volontaire : calendrier seul, pas de saisie libre),
 * ce qui fait échouer le contrôle d'"editability" de Playwright. `setDate(..., true)` déclenche un
 * vrai `change`/`input` DOM sur l'input, comme une sélection manuelle dans le calendrier.
 */
export async function fillDatePicker(page: Page, selector: string, date: string): Promise<void> {
    await page.locator(selector).evaluate((input: HTMLInputElement & { _flatpickr?: { setDate: (date: string, triggerChange: boolean) => void } }, value) => {
        input._flatpickr?.setDate(value, true);
    }, date);
}

export async function loginAs(page: Page, email: string, password = FIXTURE_PASSWORD): Promise<void> {
    await page.goto('/en/');
    await waitForCsrfControllerReady(page);

    await page.locator('#username').fill(email);
    await page.locator('#password').fill(password);
    await page.getByRole('button', { name: 'Sign in' }).click();

    // Timeout généreux : en dev, AssetMapper sert des dizaines de modules JS non bundlés via le
    // serveur PHP mono-thread intégré, ce qui ralentit le chargement de la page suivante (non
    // représentatif de la prod, où les assets sont compilés).
    await expect(page).toHaveURL(/\/fr\/tableau-de-bord/, { timeout: 15_000 });
}

/**
 * Valide le formulaire d'enregistrement de séance et attend la page de la séance créée. La
 * vérification des doublons de date (date_controller.js) peut afficher une modale d'information
 * juste avant la fenêtre de note, si l'utilisateur a déjà une séance ce jour-là (fixtures à dates
 * aléatoires, cf. CLAUDE.md) : elle est fermée si elle apparaît.
 */
export async function submitWorkout(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Submit' }).click();

    const dateInfoConfirm = page.locator('.swal2-confirm');
    const noteSubmit = page.locator('#note-modal-submit');
    await expect(dateInfoConfirm.or(noteSubmit).filter({ visible: true }).first()).toBeVisible();
    if (await dateInfoConfirm.isVisible()) {
        await dateInfoConfirm.click();
    }

    await noteSubmit.click();
    await expect(page).toHaveURL(/\/en\/workout\/[0-9a-f-]+/, { timeout: 15_000 });
}

/**
 * Ouvre le sélecteur d'exercices, coche les `count` premiers exercices distincts de la liste et
 * les ajoute à la séance. Un exercice peut apparaître deux fois (« Tes habituels » + liste
 * complète) : les doublons sont ignorés. Retourne les identifiants cochés, dans l'ordre.
 */
export async function addExercises(page: Page, count: number): Promise<string[]> {
    await openExerciseSelector(page);
    const allIds = await exerciseCheckboxes(page).evaluateAll((inputs) => inputs.map((input) => (input as HTMLInputElement).value));
    const ids = [...new Set(allIds)].slice(0, count);

    await checkAndAdd(page, ids);

    return ids;
}

/**
 * Ajoute des exercices précis (identifiants), dans cet ordre — l'ordre de la liste peut changer
 * d'une séance à l'autre avec la section « Tes habituels ».
 */
export async function addExercisesByIds(page: Page, ids: string[]): Promise<void> {
    await openExerciseSelector(page);
    await checkAndAdd(page, ids);
}

function exerciseCheckboxes(page: Page) {
    return page.locator('input[data-model="norender|selectedIds[]"]');
}

async function openExerciseSelector(page: Page): Promise<void> {
    await page.locator('[data-live-action-param="open"]').click();
    await expect(exerciseCheckboxes(page).first()).toBeAttached();
}

async function checkAndAdd(page: Page, ids: string[]): Promise<void> {
    const cardsBefore = await page.locator('[data-exercise-index]').count();

    for (const id of ids) {
        await page.locator(`label:has(input[value="${id}"])`).first().click();
    }
    await page.locator('[data-exercise-selector-target="addButton"]').click();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(cardsBefore + ids.length);
}
