import { test, expect, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Une séance enregistrée aujourd'hui (date du jour pré-remplie, heure courante) devient la
// dernière performance de son exercice : l'ajouter de nouveau doit reprendre ses séries.
// Crée une séance sur `dashboardSingle` (compte dédié aux scénarios qui écrivent, pour ne pas
// entrer en concurrence avec `workout.spec.ts` sur `workout11` en exécution parallèle) :
// recharger les fixtures de test après un run e2e.

async function addFirstExercise(page: Page): Promise<void> {
    await page.getByText('Add an exercise').click();
    await page.locator('[data-live-action-param="selectExercise"]').first().click();
    await expect(page.locator('[data-exercise-index]')).toHaveCount(1);
}

test('a new exercise card is prefilled with the last performance', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.dashboardSingle.email);

    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');
    await addFirstExercise(page);
    const weight = page.locator('input[name*="[exerciseSets][0][weight]"]');
    const reps = page.locator('input[name*="[exerciseSets][0][reps]"]');
    await weight.fill('77.5');
    await reps.fill('7');
    await page.getByRole('button', { name: 'Submit' }).click();
    await page.locator('#note-modal-submit').click();
    await expect(page).toHaveURL(/\/en\/workout\/[0-9a-f-]+/, { timeout: 15_000 });

    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');
    await addFirstExercise(page);

    await expect(weight).toHaveValue('77.5');
    await expect(reps).toHaveValue('7');
    await expect(page.locator('[data-exercise-index]')).toContainText('Carried over from your workout on');

    await page.getByRole('button', { name: 'Clear the sets carried over from your last workout' }).click();

    await expect(page.locator('.js-sets-tbody tr')).toHaveCount(1);
    await expect(weight).toHaveValue('');
    await expect(reps).toHaveValue('');
    await expect(page.locator('[data-exercise-index]')).not.toContainText('Carried over from your workout on');
});
