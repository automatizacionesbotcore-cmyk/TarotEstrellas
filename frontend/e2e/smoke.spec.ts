import { test, expect } from '@playwright/test';

test.describe('Smoke público', () => {
  test('home carga y muestra branding TarotEstrellas', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/Tarot/i);
  });

  test('navegación a login', async ({ page }) => {
    await page.goto('/');
    const loginLink = page.getByRole('link', { name: /iniciar sesi[oó]n|login/i }).first();
    if (await loginLink.count()) {
      await loginLink.click();
      await expect(page).toHaveURL(/auth\/login/);
    }
  });
});
