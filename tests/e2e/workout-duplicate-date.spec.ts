import { test, expect, type Page } from '@playwright/test';
import { FIXTURE_USERS, addExercises, loginAs } from './helpers';

// La date du jour est pré-remplie sans émettre de `change` : la vérification des doublons de
// date doit quand même avoir lieu, au clic sur « Submit », avant la fenêtre de note.
// Enregistre une séance : recharger les fixtures de test après un run e2e.

async function startWorkoutWithOneSet(page: Page): Promise<void> {
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');
    await addExercises(page, 1);
    await page.locator('input[name*="[exerciseSets][0][weight]"]').fill('50');
    await page.locator('input[name*="[exerciseSets][0][reps]"]').fill('10');
}

test('submitting a second workout today warns about the one already logged', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.noWorkout.email);

    await startWorkoutWithOneSet(page);
    await page.getByRole('button', { name: 'Submit' }).click();
    await expect(page.locator('.swal2-popup')).toHaveCount(0);
    await page.locator('#note-modal-submit').click();
    await expect(page).toHaveURL(/\/en\/workout\/[0-9a-f-]+/, { timeout: 15_000 });

    await startWorkoutWithOneSet(page);
    await page.getByRole('button', { name: 'Submit' }).click();

    await expect(page.locator('.swal2-popup')).toContainText('You already have 1 workout logged on this date.');
    await expect(page.locator('#note-modal')).toBeHidden();
    await page.locator('.swal2-confirm').click();
    await expect(page.locator('#note-modal')).toBeVisible();
});
