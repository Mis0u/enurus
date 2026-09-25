import { test, expect } from '@playwright/test';
import { FIXTURE_USERS, addExercises, addExercisesByIds, loginAs, submitWorkout } from './helpers';

// Une séance enregistrée aujourd'hui (date du jour pré-remplie, heure courante) devient la
// dernière performance de son exercice : l'ajouter de nouveau doit reprendre ses séries.
// Crée une séance sur `dashboardSingle` (compte dédié aux scénarios qui écrivent, pour ne pas
// entrer en concurrence avec `workout.spec.ts` sur `workout11` en exécution parallèle) :
// recharger les fixtures de test après un run e2e.

test('a new exercise card is prefilled with the last performance', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.dashboardSingle.email);

    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');
    const [exerciseId] = await addExercises(page, 1);
    const weight = page.locator('input[name*="[exerciseSets][0][weight]"]');
    const reps = page.locator('input[name*="[exerciseSets][0][reps]"]');
    await weight.fill('77.5');
    await reps.fill('7');
    await submitWorkout(page);

    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');
    await addExercisesByIds(page, [exerciseId]);

    await expect(weight).toHaveValue('77.5');
    await expect(reps).toHaveValue('7');
    await expect(page.locator('[data-exercise-index]')).toContainText('Carried over from your workout on');

    await page.getByRole('button', { name: 'Clear the sets carried over from your last workout' }).click();

    await expect(page.locator('.js-sets-tbody tr')).toHaveCount(1);
    await expect(weight).toHaveValue('');
    await expect(reps).toHaveValue('');
    await expect(page.locator('[data-exercise-index]')).not.toContainText('Carried over from your workout on');
});
