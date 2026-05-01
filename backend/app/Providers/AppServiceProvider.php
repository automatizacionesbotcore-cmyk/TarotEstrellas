<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSending::class,
            [\App\Listeners\RegistrarEmailEnviado::class, 'handleSending']
        );
        \Illuminate\Support\Facades\Event::listen(
            \Illuminate\Mail\Events\MessageSent::class,
            [\App\Listeners\RegistrarEmailEnviado::class, 'handleSent']
        );

        // Audit observers (M2) — all key models
        $observer = \App\Observers\AuditableObserver::class;
        \App\Models\User::observe($observer);
        \App\Models\Cita::observe($observer);
        \App\Models\Pago::observe($observer);
        \App\Models\Reembolso::observe($observer);
        \App\Models\ComprobanteTransferencia::observe($observer);
        \App\Models\CuentaBancaria::observe($observer);
        \App\Models\Cupon::observe($observer);
        \App\Models\Membresia::observe($observer);
        \App\Models\AppSetting::observe($observer);
        \App\Models\PlantillaNotificacion::observe($observer);
        \App\Models\TipoConsulta::observe($observer);
        \App\Models\TipoConsultaPrecio::observe($observer);
        \App\Models\DisponibilidadBase::observe($observer);
        \App\Models\BloqueoAgenda::observe($observer);
        \App\Models\UserRole::observe($observer);
        \App\Models\PerfilEspecialista::observe($observer);
    }
}
