<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AlertaConsumoApiMail extends Mailable
{
    use Queueable, SerializesModels, \App\Mail\Concerns\TaggedMail;

    /**
     * @param  array  $row  ['provider'=>..., 'usado'=>..., 'limite'=>..., 'porcentaje'=>..., 'unidad'=>..., 'estado'=>...]
     */
    public function __construct(public array $row, public string $periodo) {}

    public function build(): self
    {
        $nivel = $this->row['estado'] === 'exceeded' ? '🚨 LÍMITE SUPERADO' : '⚠️ Cerca del límite';
        $subject = "{$nivel} · API {$this->row['provider']} · {$this->row['porcentaje']}%";

        return $this
            ->subject($subject)
            ->tagNotif('alerta_api_'.$this->row['estado'])
            ->view('emails.alerta-consumo-api', [
                'row'     => $this->row,
                'periodo' => $this->periodo,
            ]);
    }
}
