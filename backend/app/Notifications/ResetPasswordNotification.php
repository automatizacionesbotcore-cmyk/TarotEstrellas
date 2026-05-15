<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

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
            ->view('emails.reset-password', [
                'saludo' => $saludo,
                'appName' => $appName,
                'resetUrl' => $resetUrl,
                'expira' => $expira,
            ]);
    }
}
