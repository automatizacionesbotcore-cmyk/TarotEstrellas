<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            TipoConsultaSeeder::class,
            TipoConsultaPrecioSeeder::class,
            AppSettingSeeder::class,
            DisponibilidadBaseSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DevUserSeeder::class);
        }
    }
}

