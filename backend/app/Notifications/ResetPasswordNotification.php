<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public string $token;

    public function __construct(string $token)
    {
        $this->token = $token;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $frontendUrl = rtrim(config('app.frontend_url') ?? env('FRONTEND_URL', config('app.url')), '/');
        $resetUrl = $frontendUrl . '/auth/reset-password?token=' . $this->token . '&email=' . urlencode($notifiable->getEmailForPasswordReset());
        $expira = (int) config('auth.passwords.' . config('auth.defaults.passwords') . '.expire', 60);

        $appName = config('app.name', 'TarotEstrellas');
        $nombre = trim((string) ($notifiable->profile?->nombre ?? $notifiable->name ?? ''));
        $saludo = $nombre !== '' ? '¡Hola ' . $nombre . '!' : '¡Hola!';

        return (new MailMessage)
            ->subject('Restablece tu contraseña · ' . $appName)
            ->greeting($saludo)
            ->line('Recibimos una solicitud para restablecer la contraseña de tu cuenta en **' . $appName . '** 🌙')
            ->line('Para crear una nueva contraseña, haz clic en el botón a continuación:')
            ->action('Restablecer contraseña', $resetUrl)
            ->line('Este enlace expirará en ' . $expira . ' minutos por motivos de seguridad.')
            ->line('Si tú no solicitaste este cambio, puedes ignorar este correo y tu contraseña seguirá siendo la misma.')
            ->salutation('Con luz y energía,  ' . "\n" . 'El equipo de ' . $appName . ' ✨');
    }
}
