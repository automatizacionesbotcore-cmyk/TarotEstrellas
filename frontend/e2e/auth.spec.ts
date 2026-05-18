import { test, expect } from '@playwright/test';

test.describe('Auth flow', () => {
  const ts = Date.now();
  const email = `e2e-${ts}@example.com`;
  const password = 'Test1234!aA';

  test('registro habilita envío con datos válidos y login carga correctamente', async ({ page }) => {
    await page.goto('/auth/register', { waitUntil: 'domcontentloaded' });
    await page.getByLabel(/^nombre/i).fill('Ester');
    await page.getByLabel(/^apellido/i).fill('Prueba');
    await page.getByLabel(/correo|email/i).fill(email);
    await page.getByLabel(/contraseña|password/i).first().fill(password);
    const passwordConfirm = page.getByLabel(/confirmar|repite/i).first();
    if (await passwordConfirm.count()) {
      await passwordConfirm.fill(password);
    }

    for (const legalButton of [
      /leer y aceptar términos/i,
      /leer y aceptar política/i,
    ]) {
      await page.getByRole('button', { name: legalButton }).click();
      const legalScroll = page.locator('.legal-scroll');
      await legalScroll.evaluate((el) => { el.scrollTop = el.scrollHeight; });
      await page.getByRole('button', { name: /^aceptar$/i }).click();
    }

    await page.getByLabel(/mayor de 18/i).check();
    await expect(page.locator('form.auth-form').getByRole('button', { name: /^crear cuenta$/i })).toBeEnabled();

    await page.goto('/auth/login', { waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('heading', { name: /iniciar sesi[oó]n/i })).toBeVisible();
    await page.getByLabel(/correo|email/i).fill(email);
    await page.getByLabel(/contraseña|password/i).fill(password);
    await expect(page.getByRole('button', { name: /ingresar|iniciar sesi[oó]n|entrar|login/i })).toBeEnabled();
  });
});
