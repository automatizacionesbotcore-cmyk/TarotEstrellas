<?php

namespace App\Mail;

use App\Models\Resumen;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResumenListoMail extends Mailable
{
    use Queueable, SerializesModels;

    public Resumen $resumen;

    public function __construct(Resumen $resumen)
    {
        $this->resumen = $resumen;
    }

    public function build(): self
    {
        return $this
            ->subject('Tu resumen de TarotEstrellas esta listo')
            ->view('emails.resumen-listo', [
                'resumen' => $this->resumen,
                'cita' => $this->resumen->cita,
            ]);
    }
}
