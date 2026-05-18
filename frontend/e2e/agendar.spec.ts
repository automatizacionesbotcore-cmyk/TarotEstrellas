import { test, expect } from '@playwright/test';

test.describe('Área privada', () => {
  test('redirige a login si no hay sesión', async ({ page }) => {
    await page.goto('/app', { waitUntil: 'domcontentloaded' });
    await expect(page).toHaveURL(/\/auth\/login/);
    await expect(page.getByRole('heading', { name: /iniciar sesi[oó]n/i })).toBeVisible();
  });
});
