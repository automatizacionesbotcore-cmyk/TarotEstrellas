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

        // Audit observers (M2)
        $observer = \App\Observers\AuditableObserver::class;
        \App\Models\Cita::observe($observer);
        \App\Models\Pago::observe($observer);
        \App\Models\Reembolso::observe($observer);
        \App\Models\Cupon::observe($observer);
        \App\Models\AppSetting::observe($observer);
        \App\Models\PlantillaNotificacion::observe($observer);
    }
}
