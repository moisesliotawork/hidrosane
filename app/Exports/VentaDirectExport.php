<?php

namespace App\Exports;

use App\Models\Venta;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;

class VentaDirectExport implements FromQuery, WithMapping, WithHeadings
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        // Cargamos todas las relaciones necesarias para que no dé errores
        return $this->query->with(['customer', 'ventaOfertas.productos.producto', 'comercial']);
    }

    public function headings(): array
    {
        return [
            'ID',
            'Fecha Venta',
            'Nº Contrato',
            'Nombre',
            'Apellidos',
            'Teléfono',
            'Teléfono 2',
            'Dirección',
            'Localidad/Ayunt.',
            'DNI',
            'Importe Total',
            'Productos Vendidos',
            'Estado Venta',
            'Status',
            'Financiera',
            'Comercial'
        ];
    }

    public function map($venta): array
    {
        return [
            $venta->id,
            $venta->fecha_venta,
            $venta->nro_contrato,
            $venta->customer?->first_names,
            $venta->customer?->last_names,
            $venta->customer?->phone,
            $venta->customer?->secondary_phone,
            $venta->customer?->primary_address,
            $venta->customer?->ciudad,
            $venta->customer?->dni,
            $venta->importe_total,

            // Productos (Concatenados)
            $venta->ventaOfertas->flatMap(function ($ventaOferta) {
                return $ventaOferta->productos->map(function ($ventaProducto) {
                    $nombre = $ventaProducto->producto?->nombre ?? 'Producto desconocido';
                    $cantidad = $ventaProducto->cantidad;
                    return "{$nombre} (x{$cantidad})";
                });
            })->unique()->implode(', '),

            // CORRECCIÓN AQUÍ: Usamos ?->value para sacar el texto del Enum
            // Si prefieres el texto bonito (ej: "En Revisión"), usa ?->label() o ?->getLabel() si tu Enum lo tiene.
            $venta->estado_venta?->value,
            $venta->status?->value ?? $venta->status, // Por si status es string o enum
            $venta->financiera?->value,

            $venta->comercial?->name,
        ];
    }
}
