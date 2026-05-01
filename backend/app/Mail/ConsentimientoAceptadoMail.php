<?php

namespace App\Mail;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ConsentimientoAceptadoMail extends Mailable
{
    use Queueable, SerializesModels, \App\Mail\Concerns\TaggedMail;

    public string $aceptadoEn;

    public function __construct(
        public User   $user,
        public string $ip,
        public string $version,
        public array  $terminos,
        public array  $privacidad,
    ) {
        $this->aceptadoEn = now()->toIso8601String();
    }

    public function build(): self
    {
        $user       = $this->user;
        $ip         = $this->ip;
        $version    = $this->version;
        $terminos   = $this->terminos;
        $privacidad = $this->privacidad;
        $aceptadoEn = $this->aceptadoEn;

        $pdf = Pdf::loadView(
            'pdf.consentimiento-aceptado',
            compact('user', 'ip', 'version', 'terminos', 'privacidad', 'aceptadoEn')
        );

        return $this
            ->subject('✨ Confirmación de aceptación de términos — TarotEstrellas')
            ->tagNotif('consentimiento_aceptado', $this->user->id)
            ->view('emails.consentimiento-aceptado', [
                'user'       => $user,
                'ip'         => $ip,
                'version'    => $version,
                'aceptadoEn' => $aceptadoEn,
            ])
            ->attachData(
                $pdf->output(),
                'terminos-aceptados.pdf',
                ['mime' => 'application/pdf']
            );
    }
}
