import { test, expect } from '@playwright/test';

const routes = [
  '/',
  '/servicios',
  '/auth/login',
  '/auth/register',
  '/app',
  '/app/admin/disponibilidad',
  '/app/admin/audit-log',
  '/app/admin/notificaciones',
];

const viewports = [
  { width: 390, height: 844, label: 'mobile' },
  { width: 768, height: 1024, label: 'tablet' },
  { width: 1366, height: 768, label: 'desktop' },
];

test.describe('Responsive UI smoke', () => {
  for (const viewport of viewports) {
    for (const route of routes) {
      test(`${route} no genera overflow horizontal en ${viewport.label}`, async ({ page }) => {
        await page.setViewportSize({ width: viewport.width, height: viewport.height });
        await page.goto(route, { waitUntil: 'domcontentloaded' });

        await expect(page.locator('body')).toBeVisible();
        await page.waitForFunction(() => document.body.innerText.trim().length > 20);

        const metrics = await page.evaluate(() => ({
          clientWidth: document.documentElement.clientWidth,
          scrollWidth: document.documentElement.scrollWidth,
          bodyTextLength: document.body.innerText.trim().length,
        }));

        expect(metrics.bodyTextLength).toBeGreaterThan(20);
        expect(metrics.scrollWidth).toBeLessThanOrEqual(metrics.clientWidth + 2);
      });
    }
  }

  test('Astrea publico mantiene controles visibles en mobile', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    await page.getByRole('button', { name: /abrir asistente astrea/i }).click();

    const dialog = page.getByRole('dialog', { name: /astrea/i });
    await expect(dialog).toBeVisible();
    await expect(page.getByRole('button', { name: /pantalla completa/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /^cerrar$/i })).toBeVisible();

    const dialogBox = await dialog.boundingBox();
    expect(dialogBox?.y ?? -1).toBeGreaterThanOrEqual(0);

    await page.getByRole('button', { name: /pantalla completa/i }).click();
    await expect(page.getByRole('button', { name: /restaurar chat/i })).toBeVisible();

    await page.getByRole('button', { name: /^cerrar$/i }).click();
    await expect(dialog).toBeHidden();
  });
});
