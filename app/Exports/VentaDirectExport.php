<?php

namespace App\Exports;

use App\Models\Venta;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
// 👇 1. AÑADE ESTAS 3 LÍNEAS IMPORTTANTES
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

// 👇 2. AÑADE ", WithStyles, ShouldAutoSize" AQUÍ
class VentaDirectExport implements FromQuery, WithMapping, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
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
            'Piso No.',
            'Localidad/Ayunt.',
            'Provincia',
            'CP',
            'DNI',
            'Importe Total',
            'Productos Vendidos',
            'Estado Venta',
            'Status',
            'Financiera',
            'Como van pasadas las finacieras',
            'Comercial',
            'Compañero',
        ];
    }

    // 👇 3. AÑADE ESTA FUNCIÓN PARA LA NEGRITA
    public function styles(Worksheet $sheet)
    {
        return [
            // Pone la fila 1 en Negrita (Bold)
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function map($venta): array
    {
        // ... (Tu código del map déjalo tal cual lo tienes ahora) ...
        return [
            $venta->id,
            $venta->fecha_venta,
            $venta->nro_contr_adm,
            $venta->customer?->first_names,
            $venta->customer?->last_names,
            $venta->customer?->phone,
            $venta->customer?->secondary_phone,
            $venta->customer?->primary_address,
            $venta->customer?->nro_piso,
            $venta->customer?->ciudad,
            $venta->provincia,
            $venta->postal_code,
            $venta->customer?->dni,
            $venta->importe_total,
            $venta->ventaOfertas->flatMap(function ($ventaOferta) {
                return $ventaOferta->productos->map(function ($ventaProducto) {
                    $nombre = $ventaProducto->producto?->nombre ?? 'Producto desconocido';
                    $cantidad = $ventaProducto->cantidad;
                    return "{$nombre} (x{$cantidad})";
                });
            })->unique()->implode(', '),
            $venta->estado_venta?->value, // Recuerda mantener el fix del enum
            $venta->status?->value ?? $venta->status,
            $venta->financiera?->value,
            '', // <--- AQUÍ: Dato vacío para la columna Como van pasadas las financieras
            $venta->comercial?->name,
            $venta->companion_id,
        ];
    }
}
