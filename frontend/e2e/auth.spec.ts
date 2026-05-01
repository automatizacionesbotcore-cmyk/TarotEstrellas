import { test, expect } from '@playwright/test';

test.describe('Auth flow', () => {
  const ts = Date.now();
  const email = `e2e-${ts}@example.com`;
  const password = 'Test1234!aA';

  test('registro y login', async ({ page }) => {
    await page.goto('/auth/registro');
    await page.getByLabel(/correo|email/i).fill(email);
    await page.getByLabel(/contraseña|password/i).first().fill(password);
    const passwordConfirm = page.getByLabel(/confirmar|repite/i).first();
    if (await passwordConfirm.count()) {
      await passwordConfirm.fill(password);
    }
    await page.getByRole('button', { name: /registrarme|crear cuenta|registrarse/i }).click();
    await expect(page).toHaveURL(/\/(auth\/verify-email|app)/, { timeout: 10_000 });

    await page.goto('/auth/login');
    await page.getByLabel(/correo|email/i).fill(email);
    await page.getByLabel(/contraseña|password/i).fill(password);
    await page.getByRole('button', { name: /iniciar sesi[oó]n|entrar|login/i }).click();
    await expect(page).toHaveURL(/\/app|\/auth\/verify-email/, { timeout: 10_000 });
  });
});
