<?php

namespace Database\Seeders;

use App\Models\DisponibilidadBase;
use App\Models\User;
use Illuminate\Database\Seeder;

class DisponibilidadBaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->whereHas('roles', fn ($q) => $q->where('nombre', 'admin_especialista'))->first();
        if (! $admin) {
            $this->command->warn('No admin user found — skipping DisponibilidadBaseSeeder');
            return;
        }

        // Lun(1)-Vie(5): 10:00-18:00, Sab(6): 10:00-14:00
        $horarios = [
            ['dia_semana' => 1, 'hora_inicio' => '10:00:00', 'hora_fin' => '18:00:00'],
            ['dia_semana' => 2, 'hora_inicio' => '10:00:00', 'hora_fin' => '18:00:00'],
            ['dia_semana' => 3, 'hora_inicio' => '10:00:00', 'hora_fin' => '18:00:00'],
            ['dia_semana' => 4, 'hora_inicio' => '10:00:00', 'hora_fin' => '18:00:00'],
            ['dia_semana' => 5, 'hora_inicio' => '10:00:00', 'hora_fin' => '18:00:00'],
            ['dia_semana' => 6, 'hora_inicio' => '10:00:00', 'hora_fin' => '14:00:00'],
        ];

        foreach ($horarios as $h) {
            DisponibilidadBase::query()->updateOrCreate(
                ['especialista_id' => $admin->id, 'dia_semana' => $h['dia_semana']],
                ['hora_inicio' => $h['hora_inicio'], 'hora_fin' => $h['hora_fin'], 'activo' => true]
            );
        }
    }
}
