<?php

namespace App\Filament\Gerente\Resources\CustomerResource\Widgets;

use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Support\Enums\FontWeight;
use Illuminate\Database\Eloquent\Builder;
use App\Models\{ContratoRecuperado, Customer, Venta};
use App\Filament\Gerente\Resources\VentaResource;
use Closure;

class CustomerSalesTable extends BaseWidget
{
    protected static ?string $heading = 'HISTÓRICO DE CONTRATOS';
    protected int|string|array $columnSpan = 'full';

    /** Filament inyecta el registro actual del ViewRecord */
    public ?Customer $record = null;

    /** @var list<string>|null */
    protected ?array $nrosContratoRecuperados = null;

    protected function getTableRecordUrlUsing(): ?Closure
    {
        return fn (Venta $record): string => VentaResource::getUrl('edit', ['record' => $record]);
    }

    protected function getTableQuery(): Builder
    {
        return Venta::query()
            ->with(['note', 'comercial'])
            ->where('customer_id', $this->record?->id)
            ->latest('fecha_venta');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('nro_contrato')
                ->label('#Contrato')
                ->state(fn (Venta $r) => $this->formatContratoNumber(
                    $r->nro_contr_adm ?: ($r->nro_contrato ?: null)
                ))
                ->searchable(['nro_contrato', 'nro_contr_adm'])
                ->sortable()
                ->badge()
                ->color(fn (Venta $r) => $this->isContratoRecuperado($r) ? 'warning' : 'success')
                ->toggleable(),

            TextColumn::make('fecha_venta')
                ->label('F. Venta')
                ->date('d/m/Y')
                ->sortable()
                ->weight(FontWeight::Bold),

            TextColumn::make('importe_total')
                ->label('Importe')
                ->money('EUR', true) // cambia la moneda si corresponde
                ->sortable(),

            TextColumn::make('modalidad_pago')
                ->label('Modalidad')
                ->badge()
                ->toggleable(),

            TextColumn::make('estado_venta_label')
                ->label('Estado')
                ->badge()
                ->color(fn (Venta $r) => $r->estado_venta_color ?? 'gray')
                ->sortable(),

            TextColumn::make('fecha_entrega')
                ->label('F. Entrega')
                ->date('d/m/Y')
                ->sortable()
                ->toggleable(),

            TextColumn::make('horario_entrega')
                ->label('Horario')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('comercial.empleado_id')
                ->label('Com.')
                ->badge()
                ->color('success')
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('note.nro_nota')
                ->label('# Nota')
                ->formatStateUsing(fn ($state) => $state && strlen($state) === 5
                    ? substr($state, 0, 3).' '.substr($state, 3, 2)
                    : $state)
                ->sortable(),
        ];
    }

    protected function isTablePaginationEnabled(): bool
    {
        return true;
    }

    protected function getTableEmptyStateHeading(): ?string
    {
        return 'Sin ventas registradas';
    }

    /** Separa las 2 primeras cifras para leer mejor (ej. 834 → 83 4, 00955 → 00 955). */
    protected function formatContratoNumber(?string $value): string
    {
        $nro = trim((string) $value);
        if ($nro === '' || $nro === '—') {
            return '—';
        }

        if (mb_strlen($nro) <= 2) {
            return $nro;
        }

        return mb_substr($nro, 0, 2).' '.mb_substr($nro, 2);
    }

    protected function isContratoRecuperado(Venta $venta): bool
    {
        $nro = trim((string) ($venta->nro_contr_adm ?: ''));
        if ($nro === '') {
            return false;
        }

        return in_array($nro, $this->nrosContratoRecuperados(), true);
    }

    /** @return list<string> */
    protected function nrosContratoRecuperados(): array
    {
        if ($this->nrosContratoRecuperados !== null) {
            return $this->nrosContratoRecuperados;
        }

        $nros = Venta::query()
            ->where('customer_id', $this->record?->id)
            ->whereNotNull('nro_contr_adm')
            ->pluck('nro_contr_adm')
            ->all();

        return $this->nrosContratoRecuperados = ContratoRecuperado::nrosRecuperadosAmong($nros);
    }
}
