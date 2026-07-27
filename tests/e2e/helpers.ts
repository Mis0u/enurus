import { expect, type Page } from '@playwright/test';

// Utilisateurs créés par UserFixtures (mot de passe fixture universel, cf. CLAUDE.md).
export const FIXTURE_PASSWORD = 'pass_1234';

export const FIXTURE_USERS = {
    // UserFixtures::WORKOUT_USERS[0] — partagé avec login.spec.ts, ne pas utiliser pour un
    // scénario destructeur (suppression de compte, etc.).
    workout11: { email: 'user-fixture-11-workout@test.com', nickname: 'user-workout-11' },
    // UserFixtures::USER_DASHBOARD_SINGLE — usage dédié, sûr pour un scénario destructeur.
    dashboardSingle: { email: 'user-fixture-1-workout@test.com', nickname: 'user-dashboard-1' },
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
