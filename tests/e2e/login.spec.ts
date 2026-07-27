import { test, expect } from '@playwright/test';
import { FIXTURE_USERS, loginAs, waitForCsrfControllerReady } from './helpers';

test('user can log in and reach the dashboard', async ({ page }) => {
    await loginAs(page, FIXTURE_USERS.workout11.email);
});

test('wrong credentials show an error and stay on login page', async ({ page }) => {
    await page.goto('/en/');
    await waitForCsrfControllerReady(page);

    await page.locator('#username').fill(FIXTURE_USERS.workout11.email);
    await page.locator('#password').fill('wrong-password');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/en\/$/);
    await expect(page.getByText(/invalid credentials/i)).toBeVisible();
});
