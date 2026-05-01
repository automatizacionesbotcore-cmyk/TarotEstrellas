<?php

use App\Jobs\AvisarVencimientoMembresiasJob;
use App\Jobs\DetectarNoShowAutomaticoJob;
use App\Jobs\EnviarRecordatorioCitaJob;
use App\Jobs\ExpirarMembresiasJob;
use App\Jobs\ExpirarReservasJob;
use App\Jobs\LimpiarGrabacionesExpiradasJob;
use App\Jobs\ProcesarReembolsosPendientesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new ExpirarReservasJob())->everyMinute()->withoutOverlapping();
Schedule::job(new ProcesarReembolsosPendientesJob())->everyFiveMinutes()->withoutOverlapping();
Schedule::job(new EnviarRecordatorioCitaJob())->hourly()->withoutOverlapping();
Schedule::job(new LimpiarGrabacionesExpiradasJob())->dailyAt('03:00')->withoutOverlapping();
Schedule::job(new ExpirarMembresiasJob())->dailyAt('02:30')->withoutOverlapping();
Schedule::job(new AvisarVencimientoMembresiasJob(7))->dailyAt('09:00')->withoutOverlapping();
Schedule::job(new DetectarNoShowAutomaticoJob(15))->everyFifteenMinutes()->withoutOverlapping();
