<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
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
        VerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $appName = config('app.name', 'TarotEstrellas');
            $nombre = trim((string) ($notifiable->profile?->nombre ?? $notifiable->name ?? ''));
            $saludo = $nombre !== '' ? 'Hola, ' . $nombre : 'Hola';
            $verificationUrl = $this->frontendVerificationUrl($url);

            return (new MailMessage)
                ->subject('Verifica tu correo · ' . $appName)
                ->view('emails.verify-email', [
                    'saludo' => $saludo,
                    'verificationUrl' => $verificationUrl,
                ]);
        });

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

    private function frontendVerificationUrl(string $signedUrl): string
    {
        $frontendUrl = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $path = (string) parse_url($signedUrl, PHP_URL_PATH);
        $query = (string) parse_url($signedUrl, PHP_URL_QUERY);
        $parts = explode('/', trim($path, '/'));
        $verifyIndex = array_search('verify', $parts, true);

        parse_str($query, $queryParams);

        $params = [
            'id' => $verifyIndex !== false ? ($parts[$verifyIndex + 1] ?? '') : '',
            'hash' => $verifyIndex !== false ? ($parts[$verifyIndex + 2] ?? '') : '',
            'expires' => $queryParams['expires'] ?? null,
            'signature' => $queryParams['signature'] ?? null,
        ];

        return $frontendUrl . '/auth/verify-email?' . http_build_query(array_filter(
            $params,
            static fn ($value) => $value !== null && $value !== ''
        ));
    }
}
