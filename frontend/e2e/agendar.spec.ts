import { test, expect } from '@playwright/test';

test.describe('Agendamiento', () => {
  test('puede entrar a /app/agendar (si está autenticado)', async ({ page }) => {
    await page.goto('/app/agendar');
    if (page.url().includes('/auth/login')) {
      test.skip(true, 'requiere auth — corre auth.spec primero o configura storageState');
    }
    await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
  });
});
