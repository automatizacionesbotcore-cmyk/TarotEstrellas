# CRUD Admin de Servicios — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Allow the admin to create, edit, reorder, toggle active/inactive, and upload images for tipos de consulta (services) from the admin panel.

**Architecture:** New `EnsureAdmin` middleware protects all admin CRUD routes. New `AdminTipoConsultaController` exposes 7 API endpoints. New `AdminServiciosPage` in the frontend uses the existing `AdminTable` component plus a modal form for create/edit. Images are stored locally via Laravel storage.

**Tech Stack:** Laravel 11 + Sanctum, React 18 + TypeScript + TanStack Query + Zustand, Vite 5, existing `AdminTable`/`ConfirmDialog` components.

---

## File Structure

| Action | File | Responsibility |
|--------|------|---------------|
| Create | `backend/app/Http/Middleware/EnsureAdmin.php` | Middleware: rejects non-admin users with 403 |
| Modify | `backend/bootstrap/app.php` | Register `admin` middleware alias |
| Create | `backend/app/Http/Controllers/Api/AdminTipoConsultaController.php` | 7 endpoints: index, store, update, toggle, reorder, uploadImagen, deleteImagen |
| Modify | `backend/routes/api.php` | Add admin/tipos-consulta route group |
| Create | `backend/tests/Feature/AdminTipoConsultaTest.php` | Feature tests for all 7 endpoints + auth/validation |
| Create | `frontend/src/pages/app/admin/AdminServiciosPage.tsx` | Admin services page with table, modal form, reorder, image upload |
| Modify | `frontend/src/layouts/AdminLayout.tsx` | Add "Servicios" nav item |
| Modify | `frontend/src/App.tsx` | Add lazy import and route for AdminServiciosPage |

---

### Task 1: EnsureAdmin Middleware

**Files:**
- Create: `backend/app/Http/Middleware/EnsureAdmin.php`
- Modify: `backend/bootstrap/app.php`

- [ ] **Step 1: Create the EnsureAdmin middleware**

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        return $next($request);
    }
}
```

- [ ] **Step 2: Register the middleware alias in bootstrap/app.php**

In `backend/bootstrap/app.php`, replace the empty `withMiddleware` callback:

```php
->withMiddleware(function (Middleware $middleware) {
    //
})
```

with:

```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->alias([
        'admin' => \App\Http\Middleware\EnsureAdmin::class,
    ]);
})
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Middleware/EnsureAdmin.php backend/bootstrap/app.php
git commit -m "feat(backend): add EnsureAdmin middleware with alias registration"
```

---

### Task 2: AdminTipoConsultaController — index, store, update

**Files:**
- Create: `backend/app/Http/Controllers/Api/AdminTipoConsultaController.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Create the controller with index, store, update methods**

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TipoConsulta;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AdminTipoConsultaController extends Controller
{
    public function index(): JsonResponse
    {
        $tipos = TipoConsulta::query()
            ->orderBy('orden_visualizacion')
            ->orderBy('nombre')
            ->get();

        return response()->json(['data' => $tipos]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nombre'                      => 'required|string|max:100|unique:tipos_consulta,nombre',
            'descripcion'                 => 'required|string|max:500',
            'duracion_minutos'            => 'required|integer|min:15|max:240',
            'precio_referencial_centavos' => 'required|integer|min:0',
            'moneda'                      => 'required|string|in:CLP,USD',
            'color_hex'                   => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'requiere_datos_natales'      => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['nombre']);
        $validated['orden_visualizacion'] = (TipoConsulta::max('orden_visualizacion') ?? 0) + 1;
        $validated['activo'] = true;

        $tipo = TipoConsulta::create($validated);

        return response()->json(['data' => $tipo], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tipo = TipoConsulta::findOrFail($id);

        $validated = $request->validate([
            'nombre'                      => "required|string|max:100|unique:tipos_consulta,nombre,{$id}",
            'descripcion'                 => 'required|string|max:500',
            'duracion_minutos'            => 'required|integer|min:15|max:240',
            'precio_referencial_centavos' => 'required|integer|min:0',
            'moneda'                      => 'required|string|in:CLP,USD',
            'color_hex'                   => 'nullable|string|regex:/^#[0-9A-Fa-f]{6}$/',
            'requiere_datos_natales'      => 'boolean',
        ]);

        $tipo->update($validated);

        return response()->json(['data' => $tipo->fresh()]);
    }
}
```

- [ ] **Step 2: Add routes in api.php**

In `backend/routes/api.php`, add the import at the top:

```php
use App\Http\Controllers\Api\AdminTipoConsultaController;
```

Inside the `Route::middleware('auth:sanctum')->group(function () { ... })` block, add:

```php
Route::middleware('admin')->prefix('admin/tipos-consulta')->group(function () {
    Route::get('/', [AdminTipoConsultaController::class, 'index']);
    Route::post('/', [AdminTipoConsultaController::class, 'store']);
    Route::put('{id}', [AdminTipoConsultaController::class, 'update']);
});
```

- [ ] **Step 3: Commit**

```bash
git add backend/app/Http/Controllers/Api/AdminTipoConsultaController.php backend/routes/api.php
git commit -m "feat(backend): add AdminTipoConsultaController with index, store, update"
```

---

### Task 3: AdminTipoConsultaController — toggle, reorder, uploadImagen, deleteImagen

**Files:**
- Modify: `backend/app/Http/Controllers/Api/AdminTipoConsultaController.php`
- Modify: `backend/routes/api.php`

- [ ] **Step 1: Add toggle and reorder methods to the controller**

Append these methods to `AdminTipoConsultaController`:

```php
public function toggle(int $id): JsonResponse
{
    $tipo = TipoConsulta::findOrFail($id);
    $tipo->update(['activo' => ! $tipo->activo]);

    return response()->json(['data' => $tipo->fresh()]);
}

public function reorder(Request $request): JsonResponse
{
    $validated = $request->validate([
        'ids'   => 'required|array|min:1',
        'ids.*' => 'integer|exists:tipos_consulta,id',
    ]);

    foreach ($validated['ids'] as $position => $id) {
        TipoConsulta::where('id', $id)->update(['orden_visualizacion' => $position + 1]);
    }

    return response()->json(['message' => 'Orden actualizado.']);
}
```

- [ ] **Step 2: Add uploadImagen and deleteImagen methods**

Append these methods to `AdminTipoConsultaController`. Add `use Illuminate\Support\Facades\Storage;` at the top of the file.

```php
public function uploadImagen(Request $request, int $id): JsonResponse
{
    $tipo = TipoConsulta::findOrFail($id);

    $request->validate([
        'imagen' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    if ($tipo->imagen_url) {
        $oldPath = str_replace('/storage/', 'public/', $tipo->imagen_url);
        Storage::delete($oldPath);
    }

    $ext = $request->file('imagen')->getClientOriginalExtension();
    $filename = "{$tipo->slug}.{$ext}";
    $request->file('imagen')->storeAs('public/tipos-consulta', $filename);

    $tipo->update(['imagen_url' => "/storage/tipos-consulta/{$filename}"]);

    return response()->json(['data' => $tipo->fresh()]);
}

public function deleteImagen(int $id): JsonResponse
{
    $tipo = TipoConsulta::findOrFail($id);

    if ($tipo->imagen_url) {
        $path = str_replace('/storage/', 'public/', $tipo->imagen_url);
        Storage::delete($path);
        $tipo->update(['imagen_url' => null]);
    }

    return response()->json(['data' => $tipo->fresh()]);
}
```

- [ ] **Step 3: Add the remaining routes**

Inside the existing `Route::middleware('admin')->prefix('admin/tipos-consulta')` group in `backend/routes/api.php`, add:

```php
Route::patch('{id}/toggle', [AdminTipoConsultaController::class, 'toggle']);
Route::patch('reorder', [AdminTipoConsultaController::class, 'reorder']);
Route::post('{id}/imagen', [AdminTipoConsultaController::class, 'uploadImagen']);
Route::delete('{id}/imagen', [AdminTipoConsultaController::class, 'deleteImagen']);
```

**Important:** Place the `Route::patch('reorder', ...)` line **before** `Route::patch('{id}/toggle', ...)` to avoid `reorder` being matched as an `{id}` parameter.

- [ ] **Step 4: Commit**

```bash
git add backend/app/Http/Controllers/Api/AdminTipoConsultaController.php backend/routes/api.php
git commit -m "feat(backend): add toggle, reorder, image upload/delete to AdminTipoConsultaController"
```

---

### Task 4: Backend Feature Tests

**Files:**
- Create: `backend/tests/Feature/AdminTipoConsultaTest.php`

- [ ] **Step 1: Create the test file with auth guard tests and CRUD tests**

The test uses the same patterns as existing tests: `RefreshDatabase`, seed `DatabaseSeeder`, `makeUserWithRole` helper, `Sanctum::actingAs`.

```php
<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TipoConsulta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTipoConsultaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->getJson('/api/admin/tipos-consulta')->assertStatus(401);
    }

    public function test_non_admin_user_gets_403(): void
    {
        $cliente = $this->makeUserWithRole('cliente');
        Sanctum::actingAs($cliente);

        $this->getJson('/api/admin/tipos-consulta')->assertStatus(403);
        $this->postJson('/api/admin/tipos-consulta', [])->assertStatus(403);
    }

    public function test_admin_can_list_all_tipos(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        TipoConsulta::factory()->create(['activo' => false, 'nombre' => 'Inactivo']);

        $response = $this->getJson('/api/admin/tipos-consulta');
        $response->assertOk();

        $data = $response->json('data');
        $nombres = collect($data)->pluck('nombre')->toArray();
        $this->assertContains('Inactivo', $nombres);
    }

    public function test_admin_can_create_tipo(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => 'Lectura de Runas',
            'descripcion'                 => 'Interpretación de runas nórdicas.',
            'duracion_minutos'            => 45,
            'precio_referencial_centavos' => 3500000,
            'moneda'                      => 'CLP',
            'color_hex'                   => '#AA33FF',
            'requiere_datos_natales'      => false,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.slug', 'lectura-de-runas');
        $response->assertJsonPath('data.activo', true);

        $this->assertDatabaseHas('tipos_consulta', [
            'nombre' => 'Lectura de Runas',
            'slug'   => 'lectura-de-runas',
        ]);
    }

    public function test_store_rejects_duplicate_nombre(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $existing = TipoConsulta::first();

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => $existing->nombre,
            'descripcion'                 => 'Duplicado.',
            'duracion_minutos'            => 30,
            'precio_referencial_centavos' => 1000,
            'moneda'                      => 'CLP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('nombre');
    }

    public function test_store_rejects_invalid_duracion(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => 'Lectura Rápida',
            'descripcion'                 => 'Demasiado corta.',
            'duracion_minutos'            => 5,
            'precio_referencial_centavos' => 1000,
            'moneda'                      => 'CLP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('duracion_minutos');
    }

    public function test_store_rejects_negative_precio(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $response = $this->postJson('/api/admin/tipos-consulta', [
            'nombre'                      => 'Servicio Negativo',
            'descripcion'                 => 'Precio inválido.',
            'duracion_minutos'            => 30,
            'precio_referencial_centavos' => -100,
            'moneda'                      => 'CLP',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('precio_referencial_centavos');
    }

    public function test_admin_can_update_tipo_without_changing_slug(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create([
            'nombre' => 'Tarot Clásico',
            'slug'   => 'tarot-clasico',
        ]);

        $response = $this->putJson("/api/admin/tipos-consulta/{$tipo->id}", [
            'nombre'                      => 'Tarot Clásico Premium',
            'descripcion'                 => 'Versión premium.',
            'duracion_minutos'            => 90,
            'precio_referencial_centavos' => 5000000,
            'moneda'                      => 'CLP',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.nombre', 'Tarot Clásico Premium');
        $response->assertJsonPath('data.slug', 'tarot-clasico');
    }

    public function test_admin_can_toggle_activo(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create(['activo' => true]);

        $response = $this->patchJson("/api/admin/tipos-consulta/{$tipo->id}/toggle");
        $response->assertOk();
        $response->assertJsonPath('data.activo', false);

        $response = $this->patchJson("/api/admin/tipos-consulta/{$tipo->id}/toggle");
        $response->assertOk();
        $response->assertJsonPath('data.activo', true);
    }

    public function test_admin_can_reorder(): void
    {
        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $a = TipoConsulta::factory()->create(['orden_visualizacion' => 1]);
        $b = TipoConsulta::factory()->create(['orden_visualizacion' => 2]);
        $c = TipoConsulta::factory()->create(['orden_visualizacion' => 3]);

        $response = $this->patchJson('/api/admin/tipos-consulta/reorder', [
            'ids' => [$c->id, $a->id, $b->id],
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('tipos_consulta', ['id' => $c->id, 'orden_visualizacion' => 1]);
        $this->assertDatabaseHas('tipos_consulta', ['id' => $a->id, 'orden_visualizacion' => 2]);
        $this->assertDatabaseHas('tipos_consulta', ['id' => $b->id, 'orden_visualizacion' => 3]);
    }

    public function test_admin_can_upload_imagen(): void
    {
        Storage::fake('local');

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create(['slug' => 'tarot-test']);

        $file = UploadedFile::fake()->image('photo.jpg', 400, 400)->size(500);

        $response = $this->postJson("/api/admin/tipos-consulta/{$tipo->id}/imagen", [
            'imagen' => $file,
        ]);

        $response->assertOk();
        $this->assertNotNull($response->json('data.imagen_url'));
        Storage::disk('local')->assertExists('public/tipos-consulta/tarot-test.jpg');
    }

    public function test_admin_can_delete_imagen(): void
    {
        Storage::fake('local');

        $admin = $this->makeUserWithRole('admin_especialista');
        Sanctum::actingAs($admin);

        $tipo = TipoConsulta::factory()->create(['slug' => 'tarot-del']);

        $file = UploadedFile::fake()->image('photo.png', 400, 400);
        $this->postJson("/api/admin/tipos-consulta/{$tipo->id}/imagen", ['imagen' => $file]);

        Storage::disk('local')->assertExists('public/tipos-consulta/tarot-del.png');

        $response = $this->deleteJson("/api/admin/tipos-consulta/{$tipo->id}/imagen");
        $response->assertOk();
        $response->assertJsonPath('data.imagen_url', null);

        Storage::disk('local')->assertMissing('public/tipos-consulta/tarot-del.png');
    }

    private function makeUserWithRole(string $roleNombre): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $user->profile()->create([
            'nombre' => 'Usuario',
        ]);

        $user->roles()->syncWithoutDetaching([
            Role::query()->where('nombre', $roleNombre)->value('id') => ['asignado_en' => now()],
        ]);

        return $user;
    }
}
```

- [ ] **Step 2: Run the tests to verify they pass**

Run:
```bash
cd backend && C:/wamp64/bin/php/php8.2.26/php.exe artisan test --filter=AdminTipoConsultaTest
```

Expected: All tests pass (11 tests, 0 failures).

- [ ] **Step 3: Commit**

```bash
git add backend/tests/Feature/AdminTipoConsultaTest.php
git commit -m "test(backend): add feature tests for AdminTipoConsultaController"
```

---

### Task 5: Frontend — AdminServiciosPage (table + toggle + reorder)

**Files:**
- Create: `frontend/src/pages/app/admin/AdminServiciosPage.tsx`

- [ ] **Step 1: Create AdminServiciosPage with service table, toggle, and reorder**

```tsx
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '../../../lib/api';
import { AdminTable, type Column } from '../../../components/admin/AdminTable';
import { toast } from '../../../stores/toastStore';

type TipoConsulta = {
  id: number;
  slug: string;
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_referencial_centavos: number;
  moneda: string;
  imagen_url: string | null;
  color_hex: string | null;
  requiere_datos_natales: boolean;
  orden_visualizacion: number;
  activo: boolean;
};

type FormData = {
  nombre: string;
  descripcion: string;
  duracion_minutos: number;
  precio_referencial_centavos: number;
  moneda: string;
  color_hex: string;
  requiere_datos_natales: boolean;
};

const EMPTY_FORM: FormData = {
  nombre: '',
  descripcion: '',
  duracion_minutos: 60,
  precio_referencial_centavos: 0,
  moneda: 'CLP',
  color_hex: '#6C3FA0',
  requiere_datos_natales: false,
};

function formatPrice(centavos: number, moneda: string) {
  const valor = centavos / 100;
  return moneda === 'CLP'
    ? `$${valor.toLocaleString('es-CL', { maximumFractionDigits: 0 })}`
    : `US$${valor.toLocaleString('en-US', { minimumFractionDigits: 2 })}`;
}

export function AdminServiciosPage() {
  const queryClient = useQueryClient();
  const [editingTipo, setEditingTipo] = useState<TipoConsulta | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [form, setForm] = useState<FormData>(EMPTY_FORM);
  const [precioPesos, setPrecioPesos] = useState('');
  const [imagenFile, setImagenFile] = useState<File | null>(null);
  const [saving, setSaving] = useState(false);
  const [errors, setErrors] = useState<Record<string, string[]>>({});

  const query = useQuery({
    queryKey: ['admin', 'tipos-consulta'],
    queryFn: async () => (await api.get<{ data: TipoConsulta[] }>('/admin/tipos-consulta')).data.data,
  });

  const toggleMutation = useMutation({
    mutationFn: (id: number) => api.patch(`/admin/tipos-consulta/${id}/toggle`),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] }),
  });

  const reorderMutation = useMutation({
    mutationFn: (ids: number[]) => api.patch('/admin/tipos-consulta/reorder', { ids }),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] }),
  });

  const tipos = query.data ?? [];

  function openCreate() {
    setEditingTipo(null);
    setForm(EMPTY_FORM);
    setPrecioPesos('');
    setImagenFile(null);
    setErrors({});
    setShowModal(true);
  }

  function openEdit(tipo: TipoConsulta) {
    setEditingTipo(tipo);
    setForm({
      nombre: tipo.nombre,
      descripcion: tipo.descripcion,
      duracion_minutos: tipo.duracion_minutos,
      precio_referencial_centavos: tipo.precio_referencial_centavos,
      moneda: tipo.moneda,
      color_hex: tipo.color_hex ?? '#6C3FA0',
      requiere_datos_natales: tipo.requiere_datos_natales,
    });
    setPrecioPesos(String(tipo.precio_referencial_centavos / 100));
    setImagenFile(null);
    setErrors({});
    setShowModal(true);
  }

  async function handleSave() {
    setSaving(true);
    setErrors({});
    const payload = { ...form, precio_referencial_centavos: Math.round(Number(precioPesos) * 100) };

    try {
      if (editingTipo) {
        await api.put(`/admin/tipos-consulta/${editingTipo.id}`, payload);
        toast.success('Servicio actualizado.');
      } else {
        const res = await api.post('/admin/tipos-consulta', payload);
        const newId = (res.data as { data: TipoConsulta }).data.id;
        if (imagenFile) {
          const fd = new FormData();
          fd.append('imagen', imagenFile);
          await api.post(`/admin/tipos-consulta/${newId}/imagen`, fd);
        }
        toast.success('Servicio creado.');
      }
      setShowModal(false);
      queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] });
    } catch (err: unknown) {
      const axiosErr = err as { response?: { data?: { errors?: Record<string, string[]> } } };
      if (axiosErr.response?.data?.errors) {
        setErrors(axiosErr.response.data.errors);
      }
    } finally {
      setSaving(false);
    }
  }

  async function handleUploadImagen() {
    if (!editingTipo || !imagenFile) return;
    const fd = new FormData();
    fd.append('imagen', imagenFile);
    await api.post(`/admin/tipos-consulta/${editingTipo.id}/imagen`, fd);
    setImagenFile(null);
    queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] });
    toast.success('Imagen subida.');
  }

  async function handleDeleteImagen() {
    if (!editingTipo) return;
    await api.delete(`/admin/tipos-consulta/${editingTipo.id}/imagen`);
    queryClient.invalidateQueries({ queryKey: ['admin', 'tipos-consulta'] });
    toast.success('Imagen eliminada.');
  }

  function moveRow(index: number, direction: -1 | 1) {
    const newIndex = index + direction;
    if (newIndex < 0 || newIndex >= tipos.length) return;
    const newOrder = [...tipos];
    [newOrder[index], newOrder[newIndex]] = [newOrder[newIndex], newOrder[index]];
    reorderMutation.mutate(newOrder.map((t) => t.id));
  }

  const columns: Column<TipoConsulta>[] = [
    {
      key: 'orden',
      label: 'Orden',
      render: (row) => {
        const idx = tipos.findIndex((t) => t.id === row.id);
        return (
          <span style={{ display: 'flex', gap: '0.25rem' }}>
            <button className="btn-icon" disabled={idx === 0} onClick={(e) => { e.stopPropagation(); moveRow(idx, -1); }} aria-label="Subir">▲</button>
            <button className="btn-icon" disabled={idx === tipos.length - 1} onClick={(e) => { e.stopPropagation(); moveRow(idx, 1); }} aria-label="Bajar">▼</button>
          </span>
        );
      },
    },
    { key: 'nombre', label: 'Nombre' },
    { key: 'duracion', label: 'Duración', render: (r) => `${r.duracion_minutos} min` },
    { key: 'precio', label: 'Precio', render: (r) => formatPrice(r.precio_referencial_centavos, r.moneda) },
    {
      key: 'estado',
      label: 'Estado',
      render: (r) => (
        <button
          className={`service-pill ${r.activo ? 'estado-confirmada' : 'estado-cancelada'}`}
          onClick={(e) => { e.stopPropagation(); toggleMutation.mutate(r.id); }}
          style={{ cursor: 'pointer' }}
        >
          {r.activo ? 'Activo' : 'Inactivo'}
        </button>
      ),
    },
    {
      key: 'acciones',
      label: '',
      render: (r) => (
        <button className="btn-secondary" onClick={(e) => { e.stopPropagation(); openEdit(r); }}>
          Editar
        </button>
      ),
    },
  ];

  const DURACIONES = [15, 30, 45, 60, 90, 120, 180, 240];

  return (
    <main className="page-content">
      <header style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', flexWrap: 'wrap', gap: '1rem' }}>
        <h1>Servicios (Tipos de Consulta)</h1>
        <button className="btn-primary" onClick={openCreate}>Nuevo servicio</button>
      </header>

      <AdminTable
        columns={columns}
        rows={tipos}
        loading={query.isLoading}
        rowKey={(r) => r.id}
      />

      {showModal && (
        <div className="confirm-dialog-backdrop" role="dialog" aria-modal="true">
          <div className="confirm-dialog" style={{ maxWidth: '540px', width: '100%' }}>
            <h3>{editingTipo ? 'Editar servicio' : 'Nuevo servicio'}</h3>

            {editingTipo && (
              <p style={{ color: 'var(--text-muted)', fontSize: '0.85rem', marginBottom: '0.5rem' }}>
                Slug: <code>{editingTipo.slug}</code>
              </p>
            )}

            <div style={{ display: 'flex', flexDirection: 'column', gap: '0.75rem' }}>
              <label className="form-label">
                Nombre
                <input className="form-input" value={form.nombre} onChange={(e) => setForm({ ...form, nombre: e.target.value })} maxLength={100} />
                {errors.nombre && <span className="form-error">{errors.nombre[0]}</span>}
              </label>

              <label className="form-label">
                Descripción
                <textarea className="form-input" rows={3} value={form.descripcion} onChange={(e) => setForm({ ...form, descripcion: e.target.value })} maxLength={500} />
                {errors.descripcion && <span className="form-error">{errors.descripcion[0]}</span>}
              </label>

              <label className="form-label">
                Duración (minutos)
                <select className="form-input" value={form.duracion_minutos} onChange={(e) => setForm({ ...form, duracion_minutos: Number(e.target.value) })}>
                  {DURACIONES.map((d) => <option key={d} value={d}>{d} min</option>)}
                </select>
              </label>

              <label className="form-label">
                Precio ({form.moneda === 'CLP' ? 'pesos' : 'dólares'})
                <input className="form-input" type="number" min="0" value={precioPesos} onChange={(e) => setPrecioPesos(e.target.value)} />
                {errors.precio_referencial_centavos && <span className="form-error">{errors.precio_referencial_centavos[0]}</span>}
              </label>

              <label className="form-label">
                Moneda
                <select className="form-input" value={form.moneda} onChange={(e) => setForm({ ...form, moneda: e.target.value })}>
                  <option value="CLP">CLP</option>
                  <option value="USD">USD</option>
                </select>
              </label>

              <label className="form-label">
                Color
                <input type="color" value={form.color_hex} onChange={(e) => setForm({ ...form, color_hex: e.target.value })} />
              </label>

              <label className="form-label" style={{ flexDirection: 'row', gap: '0.5rem', alignItems: 'center' }}>
                <input type="checkbox" checked={form.requiere_datos_natales} onChange={(e) => setForm({ ...form, requiere_datos_natales: e.target.checked })} />
                Requiere datos natales
              </label>

              {/* Image section */}
              <div style={{ borderTop: '1px solid var(--border-subtle)', paddingTop: '0.75rem' }}>
                <p style={{ fontWeight: 600, marginBottom: '0.5rem' }}>Imagen</p>
                {editingTipo?.imagen_url && (
                  <div style={{ marginBottom: '0.5rem' }}>
                    <img
                      src={editingTipo.imagen_url}
                      alt={editingTipo.nombre}
                      style={{ maxWidth: '120px', borderRadius: '8px' }}
                    />
                    <button className="btn-secondary" style={{ marginLeft: '0.5rem' }} onClick={handleDeleteImagen}>Eliminar imagen</button>
                  </div>
                )}
                <input type="file" accept="image/jpeg,image/png,image/webp" onChange={(e) => setImagenFile(e.target.files?.[0] ?? null)} />
                {editingTipo && imagenFile && (
                  <button className="btn-secondary" style={{ marginTop: '0.5rem' }} onClick={handleUploadImagen}>Subir imagen</button>
                )}
              </div>
            </div>

            <div className="confirm-dialog-actions" style={{ marginTop: '1rem' }}>
              <button type="button" className="btn-secondary" onClick={() => setShowModal(false)} disabled={saving}>Cancelar</button>
              <button type="button" className="btn-primary" onClick={handleSave} disabled={saving}>
                {saving ? 'Guardando…' : 'Guardar'}
              </button>
            </div>
          </div>
        </div>
      )}
    </main>
  );
}
```

- [ ] **Step 2: Commit**

```bash
git add frontend/src/pages/app/admin/AdminServiciosPage.tsx
git commit -m "feat(frontend): add AdminServiciosPage with table, modal, reorder, image upload"
```

---

### Task 6: Frontend — Wire up routing and navigation

**Files:**
- Modify: `frontend/src/layouts/AdminLayout.tsx`
- Modify: `frontend/src/App.tsx`

- [ ] **Step 1: Add "Servicios" nav item in AdminLayout.tsx**

In `frontend/src/layouts/AdminLayout.tsx`, add this entry to the `navItems` array, after `Citas` and before `Ajustes`:

```ts
{ to: '/app/admin/servicios', label: 'Servicios', end: false },
```

The `navItems` array should look like:

```ts
const navItems = [
  { to: '/app/admin',              label: 'Resumen',      end: true  },
  { to: '/app/admin/reembolsos',   label: 'Reembolsos',   end: false },
  { to: '/app/admin/comprobantes', label: 'Comprobantes', end: false },
  { to: '/app/admin/citas',        label: 'Citas',        end: false },
  { to: '/app/admin/servicios',    label: 'Servicios',    end: false },
  { to: '/app/admin/settings',     label: 'Ajustes',      end: false },
];
```

- [ ] **Step 2: Add lazy import and route in App.tsx**

In `frontend/src/App.tsx`, add this lazy import after the existing admin page imports (around line 37):

```tsx
const AdminServiciosPage = lazy(() => import('./pages/app/admin/AdminServiciosPage').then((m) => ({ default: m.AdminServiciosPage })));
```

Inside the admin `<Route>` group (around line 132), add a new Route before the `settings` route:

```tsx
<Route path="servicios" element={<Suspense fallback={<PageLoader />}><AdminServiciosPage /></Suspense>} />
```

- [ ] **Step 3: Commit**

```bash
git add frontend/src/layouts/AdminLayout.tsx frontend/src/App.tsx
git commit -m "feat(frontend): wire AdminServiciosPage into admin routing and navigation"
```

---

### Task 7: Manual Verification and Cleanup

- [ ] **Step 1: Ensure storage symlink exists**

Run (only needed once per environment):

```bash
cd backend && C:/wamp64/bin/php/php8.2.26/php.exe artisan storage:link
```

- [ ] **Step 2: Run all backend tests**

```bash
cd backend && C:/wamp64/bin/php/php8.2.26/php.exe artisan test --filter=AdminTipoConsultaTest
```

Expected: All 11 tests pass.

- [ ] **Step 3: Build frontend and verify in browser**

```bash
cd frontend && npm run build
```

Then open the admin panel at `/app/admin/servicios` and verify:
1. All services (active and inactive) appear in the table
2. Click "Nuevo servicio" — modal opens, fill fields, click Guardar — new service appears
3. Click "Editar" on a service — modal opens with pre-filled values, slug shown as read-only text
4. Click the Estado pill — service toggles between Activo/Inactivo
5. Click the arrow buttons — services reorder
6. Upload an image in edit mode — image preview appears
7. Delete an image — image removed
8. Inactive services show in admin table (verify they don't appear in the public `/servicios` page)

- [ ] **Step 4: Final commit if any cleanup was needed**

```bash
git add -A && git commit -m "chore: admin servicios cleanup after verification"
```
