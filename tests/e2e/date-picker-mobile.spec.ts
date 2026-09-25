import { test, expect, devices, type Page } from '@playwright/test';
import { FIXTURE_USERS, loginAs } from './helpers';

// Sur mobile (user agent Android), flatpickr remplace son calendrier par le sélecteur de date
// natif (`input.flatpickr-mobile`) et n'envoie alors aucun événement `change` sur le champ
// d'origine : la vérification des doublons de date et le filtre par date de Mes séances ne se
// déclenchaient jamais sur téléphone.
test.use({ ...devices['Pixel 7'] });

async function pickNativeDate(page: Page, date: string): Promise<void> {
    await page.locator('input.flatpickr-mobile').first().fill(date);
}

test('picking a date on mobile checks for a workout already logged that day', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/log-workout');
    await page.waitForLoadState('networkidle');

    const dateCheck = page.waitForRequest((request) => request.url().includes('/log-workout/check-date?date=2026-08-01'));
    await pickNativeDate(page, '2026-08-01');

    await dateCheck;
});

test('picking a date on mobile filters the workout list', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
    await page.goto('/en/my-workouts');
    await page.waitForLoadState('networkidle');

    await pickNativeDate(page, '2026-08-01');

    await expect(page).toHaveURL(/[?&]date=2026-08-01/);
});
