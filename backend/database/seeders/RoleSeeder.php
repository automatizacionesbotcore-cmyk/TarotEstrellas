<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'nombre'      => 'super_admin',
                'descripcion' => 'Acceso total al sistema',
            ],
            [
                'nombre'      => 'admin_especialista',
                'descripcion' => 'Especialista con panel de administracion',
            ],
            [
                'nombre'      => 'cliente',
                'descripcion' => 'Usuario registrado con acceso a servicios',
            ],
        ];

        foreach ($roles as $roleData) {
            Role::updateOrCreate(
                ['nombre' => $roleData['nombre']],
                $roleData
            );
        }
    }
}
