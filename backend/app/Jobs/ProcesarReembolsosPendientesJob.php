<?php

namespace App\Jobs;

use App\Models\Reembolso;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcesarReembolsosPendientesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $limit = 100)
    {
    }

    public function handle(): void
    {
        Reembolso::query()
            ->where('estado', 'pendiente')
            ->orderBy('id')
            ->limit($this->limit)
            ->pluck('id')
            ->each(function (int $id): void {
                ProcesarReembolsoJob::dispatch($id);
            });
    }
}
