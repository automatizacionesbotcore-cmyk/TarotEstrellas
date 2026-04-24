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
            AppSettingSeeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DevUserSeeder::class);
        }
    }
}

