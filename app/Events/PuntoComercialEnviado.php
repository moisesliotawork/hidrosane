<?php

namespace App\Events;

use App\Models\PuntoComercialReport;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PuntoComercialEnviado
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public PuntoComercialReport $report
    ) {
    }
}
