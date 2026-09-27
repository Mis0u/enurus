import { test, expect, devices } from '@playwright/test';
import { loginAs } from './helpers';

// Ligne de connexion compacte sur mobile (page « Mes connexions » et widget du dashboard, même
// partial) : avatar, nom et boutons sur une seule ligne. `user-fixture-connection-9` : une seule
// connexion (le hub `fixture-26`), utilisé par aucun autre test — lecture seule, compte en anglais.
test.use({ ...devices['Pixel 7'], viewport: { width: 390, height: 844 } });

test('a connection fits on a single line with a short "View" button', async ({ page }) => {
    await loginAs(page, 'user-fixture-connection-9@test.com');
    await page.goto('/en/connections');
    await page.waitForLoadState('networkidle');

    const row = page.locator('li', { has: page.locator('a[aria-label^="View "]') }).first();
    const viewButton = row.locator('a[aria-label^="View "]');
    const nickname = row.locator('span.truncate');

    expect((await viewButton.innerText()).trim()).toBe('View');
    const [buttonBox, nicknameBox] = [await viewButton.boundingBox(), await nickname.boundingBox()];
    expect(Math.abs((buttonBox?.y ?? 0) + (buttonBox?.height ?? 0) / 2 - ((nicknameBox?.y ?? 1000) + (nicknameBox?.height ?? 0) / 2))).toBeLessThan(10);
});
