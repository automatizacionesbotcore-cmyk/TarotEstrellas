<?php

namespace Database\Seeders;

use App\Models\Consentimiento;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DevUserSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $admin = $this->createUser('Admin TarotEstrellas', 'admin@tarotestrellas.test', 'password123', $now);
        $this->attachRole($admin, 'admin_especialista', $now);
        $this->attachRole($admin, 'cliente', $now);

        $cliente = $this->createUser('Cliente Prueba', 'cliente@tarotestrellas.test', 'password123', $now);
        $this->attachRole($cliente, 'cliente', $now);

        $this->command->info('Usuarios de prueba creados:');
        $this->command->table(
            ['Rol', 'Email', 'Contraseña'],
            [
                ['admin', 'admin@tarotestrellas.test', 'password123'],
                ['cliente', 'cliente@tarotestrellas.test', 'password123'],
            ]
        );
    }

    private function createUser(string $nombre, string $email, string $password, $now): User
    {
        $user = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'uuid' => (string) Str::uuid(),
                'name' => $nombre,
                'password' => $password,
                'email_verified_at' => $now,
            ]
        );

        $user->profile()->updateOrCreate([], [
            'nombre' => $nombre,
            'idioma_preferido' => 'es',
        ]);

        $user->preferenciaNotificacion()->updateOrCreate([], [
            'zona_horaria' => 'America/Santiago',
            'canal_preferido' => 'email',
        ]);

        Consentimiento::query()->updateOrCreate(
            ['user_id' => $user->id, 'tipo' => 'privacidad'],
            [
                'version_documento' => 'v1',
                'otorgado' => true,
                'otorgado_en' => $now,
                'ip_otorgamiento' => '127.0.0.1',
                'user_agent' => 'DevSeeder',
            ]
        );

        Consentimiento::query()->updateOrCreate(
            ['user_id' => $user->id, 'tipo' => 'terminos'],
            [
                'version_documento' => 'v1',
                'otorgado' => true,
                'otorgado_en' => $now,
                'ip_otorgamiento' => '127.0.0.1',
                'user_agent' => 'DevSeeder',
            ]
        );

        return $user;
    }

    private function attachRole(User $user, string $roleName, $now): void
    {
        $role = Role::query()->where('nombre', $roleName)->firstOrFail();
        $user->roles()->syncWithoutDetaching([
            $role->id => ['asignado_en' => $now],
        ]);
    }
}
