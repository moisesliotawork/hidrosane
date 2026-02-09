<?php

namespace App\Exports;

use App\Models\Venta;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentaDirectExport implements FromQuery, WithMapping, WithHeadings, WithStyles, ShouldAutoSize
{
    protected $query;

    public function __construct($query)
    {
        $this->query = $query;
    }

    public function query()
    {
        return $this->query->with(['customer', 'ventaOfertas.productos.producto', 'comercial', 'companion']);
    }

    public function headings(): array
    {
        return [
            'Fecha Venta',
            'Nº Contrato',
            'Nombre',
            'Apellidos',
            'TELEFONOS', // 👈 Fusión de Tel 1 y 2
            'DIRECCION', // 👈 Fusión de Dirección, Piso y Localidad
            'Provincia',
            'CP',
            'DNI',
            'Importe Total',
            'Productos',
            'Estado Venta',
            'Seguimiento?',
            'Financiera',
            'Como van pasadas las financieras',
            'Comercial / Compañero', // 👈 Fusión de Comercial y Compañero
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function map($venta): array
    {
        // 1. PREPARAR TELEFONOS
        $tel1 = $venta->customer?->phone;
        $tel2 = $venta->customer?->secondary_phone;
        // Si hay teléfono 2, añade " / ", si no, deja solo el 1
        $telefonosFinal = $tel1 . ($tel2 ? " / " . $tel2 : "");

        // 2. PREPARAR DIRECCIÓN COMPLETA
        $direccion = $venta->customer?->primary_address;
        $piso = $venta->customer?->nro_piso;
        $ciudad = $venta->customer?->ciudad;

        // Formato: Calle Falsa 123 (Piso: 1B) - Madrid
        $direccionFinal = "{$direccion}" .
                          ($piso ? " (Piso: {$piso})" : "") .
                          ($ciudad ? " - {$ciudad}" : "");

        // 3. PREPARAR EQUIPO (Comercial + Compañero)
        $comercial = $venta->comercial?->name;
        $companero = $venta->companion_label; // Usamos el label que arreglamos antes
        $equipoFinal = "{$comercial} / {$companero}";

        return [
            $venta->fecha_venta,
            $venta->nro_contr_adm,
            $venta->customer?->first_names,
            $venta->customer?->last_names,

            $telefonosFinal, // Columna TELEFONOS fusionada
            $direccionFinal, // Columna DIRECCION fusionada

            $venta->customer?->provincia ?? $venta->provincia,
            $venta->customer?->postal_code ?? $venta->postal_code,
            $venta->customer?->dni,
            $venta->importe_total,

            // Lógica de productos (sin cambios)
            $venta->ventaOfertas->flatMap(function ($ventaOferta) {
                return $ventaOferta->productos->map(function ($ventaProducto) {
                    $nombre = $ventaProducto->producto?->nombre ?? 'Producto desconocido';
                    $cantidad = $ventaProducto->cantidad;
                    return "{$nombre} (x{$cantidad})";
                });
            })->unique()->implode(', '),

            $venta->estado_venta?->value,
            $venta->seguimiento, // Seguimiento
            '', // Financiera vacía
            '', // Como van pasadas... vacío

            $equipoFinal, // Columna COMERCIAL / COMPAÑERO fusionada
        ];
    }
}
