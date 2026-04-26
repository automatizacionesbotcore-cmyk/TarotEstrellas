<?php

namespace Database\Seeders;

use App\Models\PerfilEspecialista;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PerfilEspecialistaSeeder extends Seeder
{
    public function run(): void
    {
        $especialistas = User::whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))
            ->get();

        foreach ($especialistas as $user) {
            if ($user->perfilEspecialista()->exists()) {
                $this->command->info("Perfil ya existe para {$user->nombre} ({$user->email})");
                continue;
            }

            $base  = Str::slug($user->nombre ?? 'especialista');
            $slug  = $base ?: 'especialista-' . $user->id;
            $count = 0;
            while (PerfilEspecialista::where('slug', $slug)->exists()) {
                $slug = $base . '-' . (++$count);
            }

            PerfilEspecialista::create([
                'user_id'       => $user->id,
                'slug'          => $slug,
                'especialidad'  => 'Tarot',
                'activo'        => true,
                'orden_display' => 1,
            ]);

            $this->command->info("Perfil creado para {$user->nombre} ({$user->email}) → slug: {$slug}");
        }
    }
}
