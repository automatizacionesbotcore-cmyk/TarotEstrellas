// Helpers de validación / sanitización compartidos entre formularios
const NAME_REGEX = /^[A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'-]*$/;

export function sanitizeNombre(input: string, max = 60): string {
  // Solo letras (incluye acentos, ñ), espacios, apóstrofo y guion
  return input
    .replace(/[^A-Za-zÁÉÍÓÚÜÑáéíóúüñ\s'-]/g, '')
    .slice(0, max);
}

export function isNombreValido(input: string): boolean {
  return input.trim().length >= 2 && NAME_REGEX.test(input);
}

export function sanitizeDigits(input: string, max = 20): string {
  return input.replace(/\D/g, '').slice(0, max);
}

/**
 * Valida un teléfono chileno: empieza con 9 y tiene exactamente 9 dígitos.
 * Devuelve el mensaje de error o null si es válido.
 */
export function validateTelefonoCL(telefono: string): string | null {
  if (telefono.length === 0) return 'Ingresa tu número de celular.';
  if (telefono[0] !== '9') return 'En Chile el celular debe comenzar con 9.';
  if (telefono.length !== 9) return 'El celular chileno debe tener 9 dígitos.';
  return null;
}

export function validateTelefonoGenerico(telefono: string, min = 7, max = 15): string | null {
  if (telefono.length === 0) return 'Ingresa tu número de celular.';
  if (telefono.length < min) return `El teléfono debe tener al menos ${min} dígitos.`;
  if (telefono.length > max) return `El teléfono no puede exceder ${max} dígitos.`;
  return null;
}
