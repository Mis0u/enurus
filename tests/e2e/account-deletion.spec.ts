import { test, expect } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Les deux tests partagent le même fixture user (`dashboardSingle`, dédié — pas `workout11`,
// utilisé par login.spec.ts) et le second est destructeur (marque le compte pour suppression) :
// `serial` + ordre non-destructeur d'abord évite qu'un run parallèle (playwright.config.ts a
// `fullyParallel: true`) ne fasse courir les deux tests en même temps sur la même ligne User.
test.describe.configure({ mode: 'serial' });

test('wrong alias in the confirmation modal blocks the deletion request', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.dashboardSingle.email);

    await page.goto('/en/settings');
    await page.getByRole('button', { name: 'Delete my account' }).click();

    await expect(page.getByText('To confirm, type your alias below.')).toBeVisible();
    await page.getByPlaceholder('Your alias').fill('not-the-real-alias');
    await page.getByRole('button', { name: 'Delete permanently' }).click();

    await expect(page.getByText('The alias does not match')).toBeVisible();
    await expect(page).toHaveURL(/\/en\/settings/);
});

test('user can request account deletion via the type-to-confirm modal', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.dashboardSingle.email);

    await page.goto('/en/settings');
    await page.getByRole('button', { name: 'Delete my account' }).click();

    // Modale SweetAlert2 (settings--account-deletion_controller.js) : confirmation par saisie du
    // nickname exact, sinon le bouton "Delete permanently" affiche une erreur de validation sans
    // envoyer la requête.
    await page.getByPlaceholder('Your alias').fill(FIXTURE_USERS.dashboardSingle.nickname);
    await page.getByRole('button', { name: 'Delete permanently' }).click();

    // Sur succès, le controller redirige vers app_logout (cf.
    // assets/controllers/settings/account_deletion_controller.js).
    await expect(page).toHaveURL(/\/en\/$/, { timeout: 15_000 });
});
