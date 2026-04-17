import { z } from 'zod';

export const loginSchema = z.object({
  email: z.email('Ingresa un correo valido.'),
  password: z.string().min(8, 'La contrasena debe tener minimo 8 caracteres.'),
});

export const registerSchema = z.object({
  nombre: z.string().min(2, 'Ingresa tu nombre.'),
  email: z.email('Ingresa un correo valido.'),
  password: z
    .string()
    .min(8)
    .regex(/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[\W_]).+$/, 'Incluye mayuscula, minuscula, numero y simbolo.'),
  password_confirmation: z.string(),
}).refine((data) => data.password === data.password_confirmation, {
  path: ['password_confirmation'],
  message: 'Las contrasenas no coinciden.',
});
