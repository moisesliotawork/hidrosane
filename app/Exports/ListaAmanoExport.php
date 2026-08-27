<?php

namespace App\Exports;

use App\Models\ListaAmano;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ListaAmanoExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        protected ?string $yearMonth = null,
        protected ?string $clienteQ = null,
        protected bool $showAll = true,
    ) {}

    public function collection(): Collection
    {
        return $this->baseQuery()->orderBy('id')->get();
    }

    protected function baseQuery(): Builder
    {
        $query = ListaAmano::query();

        if (! $this->showAll && filled($this->yearMonth)) {
            try {
                [$year, $month] = array_map('intval', explode('-', $this->yearMonth));
                $query->where('anio', $year)->where('mes', $month);
            } catch (\Throwable) {
                // ignore invalid period
            }
        }

        $q = trim((string) ($this->clienteQ ?? ''));
        if ($q !== '') {
            $query->where('cliente', 'like', '%'.$q.'%');
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'ID',
            'MES',
            'pagina',
            'nro',
            'cliente',
            'comercial_1',
            'comercial_2',
            'detalle',
            'observaciones',
        ];
    }

    /**
     * @param  ListaAmano  $row
     */
    public function map($row): array
    {
        return [
            $row->id,
            $row->mes_codigo,
            $row->pagina,
            $row->nro,
            $row->cliente,
            $row->comercial_1,
            $row->comercial_2,
            $row->detalle,
            $row->observaciones,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
