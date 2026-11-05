import { test, expect, devices } from '@playwright/test';

// Calculateur de 1RM public, sur mobile : le calcul se fait dans le navigateur, à chaque frappe.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

const oneRepMax = '[data-one-rep-max--calculator-target="oneRepMax"]';
const loadRows = '[data-one-rep-max--calculator-target="loads"] tr';

test('a visitor estimates their one-rep max and their training loads', async ({ page }) => {
    await page.goto('/fr/calculateur-1rm');

    await expect(page.locator(oneRepMax)).toHaveText('96');
    await expect(page.locator(loadRows)).toHaveCount(9);

    await page.getByLabel('Poids soulevé').fill('100');
    await page.getByLabel('Répétitions').fill('10');
    await expect(page.locator(oneRepMax)).toHaveText('133,3');

    await page.getByRole('button', { name: 'lbs' }).click();
    await expect(page.locator(loadRows).first()).toContainText('135 lbs');
});

test('the calculator fits a phone screen without scrolling sideways', async ({ page }) => {
    await page.goto('/fr/calculateur-1rm');

    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow).toBeLessThanOrEqual(0);
});
