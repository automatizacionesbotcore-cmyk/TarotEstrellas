# CRUD Admin de Servicios (Tipos de Consulta)

## Objetivo

Permitir a la especialista (admin) crear, editar, reordenar, activar/desactivar y subir imágenes de los tipos de consulta desde el panel admin, sin tocar base de datos ni seeders.

## Decisiones de diseño

- **Sin eliminación**: los servicios solo se desactivan (`activo = false`). No se usa soft delete ni hard delete. Esto preserva la integridad referencial con citas históricas.
- **Imágenes en storage local**: se almacenan en `storage/app/public/tipos-consulta/` y se sirven vía symlink público de Laravel (`php artisan storage:link`).
- **Slug auto-generado**: al crear un servicio, el slug se genera automáticamente desde el nombre con `Str::slug()`. En edición no se modifica para no romper URLs existentes.
- **Middleware admin**: se crea `EnsureAdmin` para proteger rutas admin en vez de verificar en cada método del controlador.

## Backend

### Middleware

**Nuevo archivo**: `app/Http/Middleware/EnsureAdmin.php`

Verifica `$request->user()->isAdmin()`. Si no es admin, retorna 403 con `{ "message": "No autorizado." }`.

Se registra en `bootstrap/app.php` como alias `admin`.

### Controlador

**Nuevo archivo**: `app/Http/Controllers/Api/AdminTipoConsultaController.php`

#### Endpoints

| Método | Ruta | Método controlador | Descripción |
|--------|------|--------------------|-------------|
| GET | `/api/admin/tipos-consulta` | `index` | Lista todos (activos e inactivos), ordenados por `orden_visualizacion` |
| POST | `/api/admin/tipos-consulta` | `store` | Crea nuevo tipo. Slug auto-generado. `orden_visualizacion` = max + 1 |
| PUT | `/api/admin/tipos-consulta/{id}` | `update` | Edita campos del tipo (no modifica slug) |
| PATCH | `/api/admin/tipos-consulta/{id}/toggle` | `toggle` | Invierte el valor de `activo` |
| PATCH | `/api/admin/tipos-consulta/reorder` | `reorder` | Recibe `{ ids: [3, 1, 2] }` y actualiza `orden_visualizacion` según posición |
| POST | `/api/admin/tipos-consulta/{id}/imagen` | `uploadImagen` | Sube imagen (max 2MB, jpg/png/webp), guarda en storage, actualiza `imagen_url` |
| DELETE | `/api/admin/tipos-consulta/{id}/imagen` | `deleteImagen` | Elimina archivo y setea `imagen_url = null` |

#### Validaciones (store/update)

```
nombre:                       required|string|max:100|unique:tipos_consulta,nombre,{id}
descripcion:                  required|string|max:500
duracion_minutos:             required|integer|min:15|max:240
precio_referencial_centavos:  required|integer|min:0
moneda:                       required|string|in:CLP,USD
color_hex:                    nullable|string|regex:/^#[0-9A-Fa-f]{6}$/
requiere_datos_natales:       boolean
```

#### Validación imagen (uploadImagen)

```
imagen: required|image|mimes:jpg,jpeg,png,webp|max:2048
```

La imagen se guarda con nombre `{slug}.{extension}` en `storage/app/public/tipos-consulta/`. La URL almacenada en BD es la ruta relativa: `/storage/tipos-consulta/{slug}.{ext}`.

### Rutas

En `routes/api.php`, dentro del grupo `auth:sanctum`, agregar:

```php
Route::middleware('admin')->prefix('admin/tipos-consulta')->group(function () {
    Route::get('/', [AdminTipoConsultaController::class, 'index']);
    Route::post('/', [AdminTipoConsultaController::class, 'store']);
    Route::put('{id}', [AdminTipoConsultaController::class, 'update']);
    Route::patch('{id}/toggle', [AdminTipoConsultaController::class, 'toggle']);
    Route::patch('reorder', [AdminTipoConsultaController::class, 'reorder']);
    Route::post('{id}/imagen', [AdminTipoConsultaController::class, 'uploadImagen']);
    Route::delete('{id}/imagen', [AdminTipoConsultaController::class, 'deleteImagen']);
});
```

### Storage

Requiere ejecutar `php artisan storage:link` para crear el symlink `public/storage -> storage/app/public`. La imagen se sirve desde `http://localhost/tarotEstrella/tarotestrellas/backend/public/storage/tipos-consulta/{slug}.webp`.

## Frontend

### Nueva página: `AdminServiciosPage.tsx`

**Ruta**: `/app/admin/servicios`

**Componentes**:

1. **Tabla de servicios** (reutiliza `AdminTable`):
   - Columnas: Orden (con flechas arriba/abajo) | Nombre | Duración | Precio | Estado (pill verde/gris) | Acciones (editar, toggle)
   - Muestra TODOS los servicios (activos e inactivos)
   - Los inactivos aparecen con opacidad reducida
   - Botón "Nuevo servicio" arriba de la tabla

2. **Modal de formulario** (crear/editar):
   - Campos: nombre, descripcion (textarea), duracion_minutos (select: 15, 30, 45, 60, 90, 120, 180, 240), precio (input number en pesos, se convierte a centavos), moneda (select CLP/USD), color_hex (input color), requiere_datos_natales (checkbox)
   - Sección de imagen: preview si existe, botón subir, botón eliminar
   - Botones: Guardar / Cancelar
   - En modo edición muestra el slug como texto readonly informativo

3. **Reordenar**: flechas arriba/abajo en cada fila. Al hacer clic, se envía el nuevo orden al backend inmediatamente.

4. **Toggle activar/desactivar**: botón inline en la columna Estado. Cambia el estado sin abrir modal.

### Cambios en archivos existentes

**`AdminLayout.tsx`**: agregar entrada en `navItems`:
```ts
{ to: '/app/admin/servicios', label: 'Servicios', end: false }
```

**`App.tsx`**: agregar lazy import y ruta:
```tsx
const AdminServiciosPage = lazy(() => import('./pages/app/admin/AdminServiciosPage').then(...));
// En las rutas admin:
<Route path="servicios" element={<Suspense fallback={<PageLoader />}><AdminServiciosPage /></Suspense>} />
```

### Formato de precio en UI

El campo de precio en el formulario acepta valor en pesos (ej: 45000). Al enviar al backend se multiplica por 100 para convertir a centavos. Al recibir del backend se divide por 100 para mostrar en pesos.

## Testing

### Backend tests

Nuevo archivo `tests/Feature/AdminTipoConsultaTest.php`:

- Un usuario no autenticado recibe 401
- Un usuario cliente (no admin) recibe 403
- Admin puede listar todos los tipos (activos e inactivos)
- Admin puede crear un tipo con campos válidos y slug se auto-genera
- Validación rechaza nombre duplicado, duración fuera de rango, precio negativo
- Admin puede editar un tipo sin cambiar el slug
- Admin puede toggle activar/desactivar
- Admin puede reordenar
- Admin puede subir imagen válida y se almacena correctamente
- Admin puede eliminar imagen

### Frontend

Verificación manual en navegador:
- Crear un servicio nuevo, verificar que aparece en el catálogo público
- Editar precio y duración, verificar cambios reflejados
- Desactivar un servicio, verificar que desaparece del catálogo público
- Reordenar servicios, verificar nuevo orden en catálogo
- Subir imagen, verificar que se muestra en el detalle del servicio
