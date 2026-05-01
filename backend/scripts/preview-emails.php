<?php
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cita = new App\Models\Cita();
$cita->uuid = 'a1b2c3d4-5678-90ab-cdef-1234567890ab';
$cita->codigo_referencia = 'TE-2026-0042';
$cita->inicio_utc = Carbon\Carbon::parse('2026-05-04 21:00:00', 'UTC');
$cita->cliente_confirmo_at = null;

$tipo = new App\Models\TipoConsulta(['nombre' => 'Lectura de Tarot Completa', 'duracion_minutos' => 60]);
$cita->setRelation('tipoConsulta', $tipo);

$user = new App\Models\User();
$profile = new App\Models\UserProfile(['nombre' => 'Carolina']);
$user->setRelation('profile', $profile);
$cita->setRelation('cliente', $user);

$confirmUrl = 'https://tarotestrellas.cl/api/citas/'.$cita->uuid.'/confirmar-asistencia?expires=1714612800&signature=abc123def456';

$variantes = [
    ['min' => 4320, 'cuando' => 'en 3 días',  'file' => '3dias',  'subject' => 'Recordatorio: tu cita es en 3 dias - TarotEstrellas'],
    ['min' => 1440, 'cuando' => 'mañana',      'file' => '1dia',   'subject' => 'Recordatorio: tu cita es manana - TarotEstrellas'],
    ['min' => 60,   'cuando' => 'en 1 hora',  'file' => '1hora',  'subject' => 'Recordatorio: tu cita es en 1 hora - TarotEstrellas'],
];

$outDir = __DIR__.'/../storage/app/previews';
if (!is_dir($outDir)) mkdir($outDir, 0775, true);

foreach ($variantes as $v) {
    $html = view('emails.recordatorio-cita', [
        'cita' => $cita,
        'cuando' => $v['cuando'],
        'confirmUrl' => $confirmUrl,
        'minutosAntes' => $v['min'],
    ])->render();
    file_put_contents($outDir.'/recordatorio-'.$v['file'].'.html', "<!-- ASUNTO: {$v['subject']} -->\n".$html);
    echo "Generado: recordatorio-{$v['file']}.html\n";
}
echo "OK\n";
