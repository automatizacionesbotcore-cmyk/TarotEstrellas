import { describe, expect, it } from 'vitest';
import { registerSchema } from './validators';

describe('registerSchema', () => {
  it('acepta una contrasena fuerte y confirmacion valida', () => {
    const result = registerSchema.safeParse({
      nombre: 'Ana',
      email: 'ana@example.com',
      password: 'Passw0rd!',
      password_confirmation: 'Passw0rd!',
    });

    expect(result.success).toBe(true);
  });

  it('rechaza cuando password y confirmacion no coinciden', () => {
    const result = registerSchema.safeParse({
      nombre: 'Ana',
      email: 'ana@example.com',
      password: 'Passw0rd!',
      password_confirmation: 'Passw0rd?'
    });

    expect(result.success).toBe(false);
  });
});
