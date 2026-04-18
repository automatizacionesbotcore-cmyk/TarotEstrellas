<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use Illuminate\Database\Seeder;

class AppSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'MINUTOS_VENTANA_TRANSFERENCIA',
                'category' => 'pagos_transferencia',
                'value' => '30',
            ],
            [
                'key' => 'MINUTOS_EXTENSION_TRANSFERENCIA',
                'category' => 'pagos_transferencia',
                'value' => '10',
            ],
            [
                'key' => 'EXTENSIONES_PERMITIDAS',
                'category' => 'pagos_transferencia',
                'value' => '1',
            ],
            [
                'key' => 'TRANSFERENCIA_BANCO',
                'category' => 'pagos_transferencia',
                'value' => 'BancoEstado',
            ],
            [
                'key' => 'TRANSFERENCIA_CUENTA',
                'category' => 'pagos_transferencia',
                'value' => '1234567890',
            ],
            [
                'key' => 'TRANSFERENCIA_RUT',
                'category' => 'pagos_transferencia',
                'value' => '11111111-1',
            ],
            [
                'key' => 'MINUTOS_ANTIGUEDAD_COMPROBANTE',
                'category' => 'pagos_transferencia',
                'value' => '30',
            ],
        ];

        foreach ($defaults as $setting) {
            AppSetting::query()->updateOrCreate(
                ['key' => $setting['key']],
                [
                    'category' => $setting['category'],
                    'value' => $setting['value'],
                    'editable_admin' => true,
                ]
            );
        }
    }
}
