<?php

namespace App\Events;

use App\Models\Venta;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VentaCreada
{
    use Dispatchable, SerializesModels;

    public Venta $venta;

    public function __construct(Venta $venta)
    {
        $this->venta = $venta;
    }
}
