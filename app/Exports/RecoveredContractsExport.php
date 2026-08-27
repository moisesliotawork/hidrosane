<?php

namespace App\Exports;

use App\Models\ContratoRecoveryItem;
use App\Models\Venta;
use App\Services\ContractRecovery\ContractFromImageRecovery;
use App\Services\ContractRecovery\OrphanDocumentMatcher;
use App\Support\Filament\VentaDocumentUpload;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Registro de contratos recuperados (paso 1).
 * Los candidatos huérfanos solo se calculan si $includeOrphanHints = true (paso 2).
 */
class RecoveredContractsExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    /** @var list<array{path: string, field: string, uploaded_at: mixed, empleado_id: string, uploader_slug: string}>|null */
    protected ?array $orphanCache = null;

    public function __construct(
        protected ?string $fromDate = null,
        protected ?string $toDate = null,
        /** Solo el paso 2 (re-enganche) debe activar el inventario de huérfanos. */
        protected bool $includeOrphanHints = false,
    ) {}

    public function collection(): Collection
    {
        $items = ContratoRecoveryItem::query()
            ->with([
                'venta' => fn ($q) => $q->withTrashed()->with(['customer', 'comercial']),
                'customer',
                'comercial',
            ])
            ->where('status', ContratoRecoveryItem::STATUS_ADDED)
            ->when($this->fromDate, fn ($q) => $q->whereDate('updated_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('updated_at', '<=', $this->toDate))
            ->orderByDesc('updated_at')
            ->get();

        $taggedVentaIds = Venta::query()
            ->withTrashed()
            ->with(['customer', 'comercial'])
            ->where('observaciones_repartidor', 'like', '%'.ContractFromImageRecovery::OBSERVACION_RECUPERADO.'%')
            ->when($this->fromDate, fn ($q) => $q->whereDate('updated_at', '>=', $this->fromDate))
            ->when($this->toDate, fn ($q) => $q->whereDate('updated_at', '<=', $this->toDate))
            ->orderByDesc('updated_at')
            ->get()
            ->keyBy('id');

        $fromItemsVentaIds = $items->pluck('venta_id')->filter()->map(fn ($id) => (int) $id)->all();

        $extra = $taggedVentaIds
            ->reject(fn (Venta $venta) => in_array($venta->id, $fromItemsVentaIds, true))
            ->values()
            ->map(fn (Venta $venta) => [
                'source' => 'observacion',
                'item' => null,
                'venta' => $venta,
            ]);

        $fromItems = $items->map(fn (ContratoRecoveryItem $item) => [
            'source' => 'recovery_item',
            'item' => $item,
            'venta' => $item->venta,
        ]);

        return $fromItems->concat($extra)->values();
    }

    /**
     * @return list<string>
     */
    protected function documentSlots(): array
    {
        return OrphanDocumentMatcher::documentFields();
    }

    public function headings(): array
    {
        $labels = VentaDocumentUpload::documentFieldLabels();
        $docHeadings = array_map(
            fn (string $field): string => $labels[$field] ?? $field,
            $this->documentSlots(),
        );

        return array_merge(
            [
                'Fecha recuperación',
                'Origen registro',
                'Nº contrato admin',
                'ID venta',
                'ID cliente',
                'Cliente',
                'DNI',
                'Comercial',
                'Fecha venta',
                'Docs recovery (OCR)',
            ],
            $docHeadings,
            [
                'Pendiente re-enganchar',
                'Candidatos huérfanos',
                'Docs auto asignados',
                'Docs pendientes manual',
                'Venta borrada',
                'Observaciones',
            ],
        );
    }

    /**
     * @param  array{source: string, item: ?ContratoRecoveryItem, venta: ?Venta}  $row
     */
    public function map($row): array
    {
        /** @var ContratoRecoveryItem|null $item */
        $item = $row['item'];
        /** @var Venta|null $venta */
        $venta = $row['venta'];
        $customer = $venta?->customer ?? $item?->customer;
        $comercial = $venta?->comercial ?? $item?->comercial;

        $pending = [];
        if ($venta) {
            foreach ($this->documentSlots() as $field) {
                if (blank($venta->{$field})) {
                    $pending[] = $field;
                }
            }
        } else {
            $pending[] = 'sin_venta_vinculada';
        }

        $comercialLabel = $comercial
            ? trim(($comercial->empleado_id ?? '').' '.$comercial->name.' '.($comercial->last_name ?? ''))
            : '—';

        $clienteNombre = $customer
            ? trim(($customer->first_names ?? '').' '.($customer->last_names ?? ''))
            : ($item?->cliente_nombre ?: '—');

        $recoveredAt = $item?->updated_at ?? $venta?->updated_at;

        $orphanHints = [
            'candidatos' => '—',
            'auto' => '—',
            'pendiente_manual' => $pending === [] ? 'OK' : implode(', ', $pending),
        ];

        if ($this->includeOrphanHints && $venta) {
            try {
                $orphanHints = app(OrphanDocumentMatcher::class)
                    ->lightweightSummaryForVenta($venta, $this->orphans());
            } catch (\Throwable) {
                // Disco ausente / etc.: dejar placeholders
            }
        }

        $docPresence = array_map(
            fn (string $field): string => filled($venta?->{$field}) ? 'SÍ' : 'NO',
            $this->documentSlots(),
        );

        return array_merge(
            [
                optional($recoveredAt)?->format('d/m/Y H:i') ?? '—',
                $row['source'] === 'recovery_item' ? 'Recuperar por imagen' : 'Etiqueta observación',
                $venta?->nro_contr_adm ?: ($item?->nro_contr_adm ?: '—'),
                $venta?->id ?? ($item?->venta_id ?: '—'),
                $customer?->id ?? ($item?->customer_id ?: '—'),
                $clienteNombre,
                $customer?->dni ?: ($item?->dni ?: '—'),
                $comercialLabel,
                optional($venta?->fecha_venta)?->format('d/m/Y') ?? '—',
                (string) count($item?->documents ?? []),
            ],
            $docPresence,
            [
                $pending === [] ? 'OK (todos los docs del formulario presentes)' : implode(', ', $pending),
                $orphanHints['candidatos'],
                $orphanHints['auto'],
                $orphanHints['pendiente_manual'],
                $venta?->deleted_at ? 'SÍ' : 'NO',
                trim((string) ($venta?->observaciones_repartidor ?? '')),
            ],
        );
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    /**
     * @return list<array{path: string, field: string, uploaded_at: mixed, empleado_id: string, uploader_slug: string}>
     */
    protected function orphans(): array
    {
        if ($this->orphanCache !== null) {
            return $this->orphanCache;
        }

        $this->orphanCache = app(OrphanDocumentMatcher::class)->listOrphans();

        return $this->orphanCache;
    }
}
